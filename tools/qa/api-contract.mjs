import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {randomBytes} from 'node:crypto';
import {performance} from 'node:perf_hooks';
import {request} from 'playwright';
import Ajv2020 from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';
import {root,app,base,phpCommand,database,startServer,stopServer} from './runtime.mjs';
const prefix='m6-'+randomBytes(6).toString('hex');
const accounts=Object.fromEntries(['admin','teacher','student','other'].map(role=>[role,{email:prefix+'-'+role+'@example.test',name:'Contract '+role,password:'Qa9!'+randomBytes(24).toString('base64url')}]));
const secrets=Object.values(accounts).map(x=>x.password);
const evidence=path.join(root,'evidence/m6/api-contract.json');
const result={run:prefix,started_at:new Date().toISOString(),transport:'Real loopback HTTP; operation paths/methods and response schemas read from OpenAPI',cases:[],responses:[],persistence:[]};
const clean=value=>secrets.reduce((s,v)=>s.split(v).join('[REDACTED]'),String(value));
const save=()=>fs.writeFileSync(evidence,JSON.stringify(result,null,2)+'\n');
const spec=JSON.parse(fs.readFileSync(path.join(app,'docs/openapi.json'),'utf8'));
const operations=Object.fromEntries(Object.entries(spec.paths).flatMap(([path,methods])=>Object.entries(methods).filter(([,o])=>o.operationId).map(([method,o])=>[o.operationId,{...o,path,method:method.toUpperCase()}])));
const ajv=new Ajv2020({strict:false,allErrors:true}); addFormats(ajv);
ajv.addSchema({$id:'urn:penguin:contract',components:spec.components});
function schemaFor(schema){return JSON.parse(JSON.stringify(schema).replaceAll('"#/components/','"urn:penguin:contract#/components/'));}
const validators=new Map();
const tokens={}; const contexts=[]; let server, studentExpiresAt;
async function call(id,{as='admin',params={},query={},body,status=200,token}={}) {
 const op=operations[id]; assert.ok(op,'Documented operation '+id);
 const uri=op.path.replace(/\{([^}]+)\}/g,(_,key)=>encodeURIComponent(params[key]));
 const url=new URL(base+uri); for(const [key,value] of Object.entries(query)) url.searchParams.set(key,value);
 const credential=token ?? tokens[as]; const headers={Accept:'application/json'};
 if(credential)headers.Authorization='Bearer '+credential;
 if(body!==undefined)headers['Content-Type']='application/json';
 const started=performance.now(); const response=await fetch(url,{method:op.method,headers,body:body===undefined?undefined:JSON.stringify(body)});
 const text=await response.text();
 assert.equal(response.status,status,id+' expected HTTP '+status+', got '+response.status);
 assert.ok(op.responses[status],id+' response '+status+' is documented');
 const data=text?JSON.parse(text):null;
 const description=op.responses[status].$ref?spec.components.responses[op.responses[status].$ref.split('/').at(-1)]:op.responses[status];
 const schema=description.content?.['application/json']?.schema;
 if(schema) {
   const key=id+'-'+status;
   if(!validators.has(key)) validators.set(key,ajv.compile(schemaFor(schema)));
   const validate=validators.get(key);
   assert.ok(validate(data),id+' response schema: '+JSON.stringify(validate.errors));
 }else assert.equal(text,'',id+' empty response');
 result.responses.push({operation:id,method:op.method,path:op.path,status,schema_checked:!!schema,duration_ms:Math.round((performance.now()-started)*100)/100});
 return data;
}
async function step(name,fn) {const start=performance.now();try{await fn();result.cases.push({name,status:'passed',duration_ms:Math.round(performance.now()-start)});}catch(e){result.cases.push({name,status:'failed',error:clean(e.stack)});throw e;}finally{save();}console.log('PASS '+name);}
function persist(name,fn) {const state=database(prefix);assert.ok(fn(state),'MySQL: '+name);result.persistence.push({name,status:'passed'});return state;}
async function webContext(){const c=await request.newContext({baseURL:base});contexts.push(c);return c;}
async function form(c,path,body,target=path) {
 const page=await c.get(path); assert.equal(page.status(),200,'Provisioning form available');
 const html=await page.text(); const csrf=html.match(/name="_token" value="([^"]+)"/);assert.ok(csrf);
 const response=await c.post(target,{form:{_token:csrf[1],...body}});
 assert.equal(response.status(),200,'Provisioning form submitted');
 return response;
}
async function issue(role,abilities,name='Contract client'){
 const data=await call('issueToken',{as:'anonymous',body:{email:accounts[role].email,password:accounts[role].password,name,abilities,expires_in_minutes:role==='student'?1:15},status:201});
 secrets.push(data.data.access_token);return data.data;
}
try {
 phpCommand(['artisan','academy:admin','--name='+accounts.admin.name,'--email='+accounts.admin.email,'--password-stdin','--no-interaction'],accounts.admin.password+'\n',app);
 server=await startServer('api-contract');
 const admin=await webContext(),teacher=await webContext();
 await step('Accounts provisioned through administrator, Fortify and teacher-management flows',async()=>{
  const logged=await form(admin,'/login',{email:accounts.admin.email,password:accounts.admin.password});assert.match(logged.url(),/\/admin\/dashboard$/);
  await form(admin,'/admin/teachers/create',{name:accounts.teacher.name,email:accounts.teacher.email,password:accounts.teacher.password,password_confirmation:accounts.teacher.password,current_password:accounts.admin.password},'/admin/teachers');
  const loggedTeacher=await form(teacher,'/login',{email:accounts.teacher.email,password:accounts.teacher.password});assert.match(loggedTeacher.url(),/\/account\/password$/);
  const changed='Qa9!'+randomBytes(24).toString('base64url');secrets.push(changed);
  await form(teacher,'/account/password',{_method:'PUT',current_password:accounts.teacher.password,password:changed,password_confirmation:changed});accounts.teacher.password=changed;
  for(const role of ['student','other']) {const c=await webContext();const r=await form(c,'/register',{name:accounts[role].name,email:accounts[role].email,password:accounts[role].password,password_confirmation:accounts[role].password});assert.match(r.url(),/\/student\/dashboard$/);}
  persist('Supported account flows persist intended roles',s=>s.users.length===4&&s.users.find(u=>u.email===accounts.teacher.email).must_change_password===0);
 });
 const common=['courses:read','lessons:read','enrollments:read','enrollments:write','progress:read','tokens:read','tokens:revoke'];
 await step('Token issuance uses role-limited abilities and explicit expiry',async()=>{
  for(const role of ['admin','teacher','student','other']) {const abilities=[...common,...(role==='admin'||role==='teacher'?['courses:write','lessons:write','reports:read']:['progress:write'])];const issued=await issue(role,abilities);tokens[role]=issued.access_token;if(role==='student')studentExpiresAt=Date.parse(issued.token.expires_at);}
 });
 const ids=Object.fromEntries(database(prefix).users.map(u=>[u.email.split('@')[0].slice(prefix.length+1),u.id]));
 let course,second,lesson,extra,enrollment;
 await step('Course CRUD, teacher assignment, filtering and pagination match the contract',async()=>{
  course=(await call('createCourse',{body:{title:'Contract foundations',slug:prefix+'-course',summary:prefix,teacher_id:ids.teacher},status:201})).data;
  second=(await call('createCourse',{body:{title:'Private second course',slug:prefix+'-second',summary:prefix},status:201})).data;
  const updated=await call('updateCourse',{params:{course:course.id},body:{title:'Contract foundations revised'}});assert.equal(updated.data.title,'Contract foundations revised');
  await call('setCoursePublication',{params:{course:course.id},body:{published:true}});
  const list=await call('listCourses',{query:{q:prefix,per_page:1,page:1}});assert.equal(list.data.length,1);assert.equal(list.meta.total,2);
  const page2=await call('listCourses',{query:{q:prefix,per_page:1,page:2}});assert.notEqual(list.data[0].id,page2.data[0].id);
  const student=await call('showCourse',{as:'student',params:{course:course.id}});assert.equal(student.data.is_published,true);assert.equal('teacher_id' in student.data,false);assert.equal('email' in student.data.teacher,false);
  await call('showCourse',{as:'student',params:{course:second.id},status:403});
  persist('Published course and assigned teacher persisted',s=>s.courses.some(c=>c.id===course.id&&c.teacher_id===ids.teacher&&c.published_at));
 });
 await step('Lesson CRUD, publication, ordering and conflicting versions',async()=>{
  lesson=(await call('createLesson',{as:'teacher',params:{course:course.id},body:{title:'First lesson',content:'Persisted learning content'},status:201})).data;
  extra=(await call('createLesson',{as:'teacher',params:{course:course.id},body:{title:'Second lesson',content:'Another lesson'},status:201})).data;
  await call('updateLesson',{as:'teacher',params:{course:course.id,lesson:lesson.id},body:{content:'Revised persisted content'}});
  await call('setLessonPublication',{as:'teacher',params:{course:course.id,lesson:lesson.id},body:{is_published:true}});
  const detail=await call('showLesson',{as:'teacher',params:{course:course.id,lesson:lesson.id}});assert.equal(detail.data.content,'Revised persisted content');
  const list=await call('listLessons',{as:'teacher',params:{course:course.id}});assert.equal(list.data.length,2);
  const current=(await call('showCourse',{as:'teacher',params:{course:course.id}})).data;
  await call('reorderLessons',{as:'teacher',params:{course:course.id},body:{order_version:current.lesson_order_version,lesson_ids:[extra.id,lesson.id]}});
  await call('reorderLessons',{as:'teacher',params:{course:course.id},body:{order_version:current.lesson_order_version,lesson_ids:[lesson.id,extra.id]},status:409});
  persist('Lesson order, content and publication persisted',s=>s.lessons.find(l=>l.id===lesson.id).content==='Revised persisted content'&&s.lessons.find(l=>l.id===extra.id).position===1);
 });
 await step('Enrollment is idempotent; own learning access and data remain scoped',async()=>{
  enrollment=(await call('enrollStudent',{as:'student',body:{course_id:course.id},status:201})).data;
  const repeat=await call('enrollStudent',{as:'student',body:{course_id:course.id}});assert.equal(repeat.data.id,enrollment.id);
  const list=await call('listEnrollments',{as:'student'});assert.equal(list.data.length,1);assert.equal(list.data[0].student_id,ids.student);
  const other=await call('listEnrollments',{as:'other'});assert.equal(other.data.length,0);
  await call('showProgress',{as:'other',params:{enrollment:enrollment.id},status:403});
  await call('completeLesson',{as:'other',params:{enrollment:enrollment.id,lesson:lesson.id},status:403});
  await call('withdrawEnrollment',{as:'other',params:{enrollment:enrollment.id},status:403});
  const listLessons=await call('listLessons',{as:'student',params:{course:course.id}});assert.equal(listLessons.data.length,1);
  await call('showLesson',{as:'student',params:{course:course.id,lesson:extra.id},status:403});
  persist('Duplicate enrollment has one active row',s=>s.enrollments.length===1&&!s.enrollments[0].withdrawn_at);
 });
 await step('Concurrent completion requests persist one row; undo, withdrawal and re-enrollment retain expected history',async()=>{
  const params={enrollment:enrollment.id,lesson:lesson.id};
  const concurrent=await Promise.all(Array.from({length:8},()=>call('completeLesson',{as:'student',params})));
  assert.ok(concurrent.every(x=>x.data.percent===100));
  persist('Eight concurrent PUTs produce one completion',s=>s.completions.length===1&&s.completions[0].enrollment_id===enrollment.id);
  assert.equal((await call('showProgress',{as:'student',params:{enrollment:enrollment.id}})).data.percent,100);
  assert.equal((await call('undoCompletion',{as:'student',params})).data.percent,0);
  assert.equal((await call('undoCompletion',{as:'student',params})).data.percent,0);
  await call('completeLesson',{as:'student',params});
  await call('withdrawEnrollment',{as:'student',params:{enrollment:enrollment.id},status:204});
  await call('completeLesson',{as:'student',params,status:403});
  persist('Withdrawal retains one historical completion',s=>s.enrollments[0].withdrawn_at&&s.completions.length===1);
  const again=await call('enrollStudent',{as:'student',body:{course_id:course.id}});assert.equal(again.data.id,enrollment.id);assert.equal(again.data.progress.percent,100);
 });
 await step('All protected operations reject absent bearer authentication',async()=>{
  for(const [id] of Object.entries(operations)) if(id!=='issueToken') await call(id,{as:'anonymous',params:{course:course.id,lesson:lesson.id,enrollment:enrollment.id,token:999999},status:401});
 });
 await step('Token abilities and live policies reject cross-course, forged and invalid requests',async()=>{
  const limited=await issue('teacher',['courses:read']);
  await call('createCourse',{token:limited.access_token,body:{title:'Blocked',slug:prefix+'-blocked'},status:403});
  await call('updateCourse',{as:'teacher',params:{course:second.id},body:{title:'Blocked'},status:403});
  await call('updateCourse',{as:'teacher',params:{course:course.id},body:{teacher_id:ids.admin},status:422});
  await call('updateCourse',{params:{course:course.id},body:{teacher_id:ids.student},status:422});
  await call('enrollStudent',{as:'student',body:{course_id:course.id,student_id:ids.other},status:422});
  await call('enrollStudent',{body:{course_id:course.id,student_id:ids.teacher},status:422});
  await call('showLesson',{params:{course:second.id,lesson:lesson.id},status:404});
  await call('createCourse',{body:{title:'Invalid duplicate',slug:prefix+'-course'},status:422});
  await call('listCourses',{query:{per_page:101},status:422});
  await call('listCourses',{token:'invalid-local-test-token',status:401});
 });
 await step('All protected operations enforce their required token abilities',async()=>{
  const readCourses=await issue('admin',['courses:read']);
  const readTokens=await issue('admin',['tokens:read']);
  for(const [id] of Object.entries(operations)) if(id!=='issueToken') {
   const token=['listCourses','showCourse'].includes(id)?readTokens.access_token:readCourses.access_token;
   await call(id,{token,params:{course:course.id,lesson:lesson.id,enrollment:enrollment.id,token:999999},status:403});
  }
 });
 await step('Staff reports are scoped; archival preserves historical relationships',async()=>{
  await call('academyOverview');await call('academyOverview',{as:'teacher'});
  await call('academyOverview',{as:'student',status:403});
  await call('archiveLesson',{as:'teacher',params:{course:course.id,lesson:lesson.id},status:204});
  await call('showLesson',{as:'student',params:{course:course.id,lesson:lesson.id},status:404});
  assert.equal((await call('showProgress',{as:'student',params:{enrollment:enrollment.id}})).data.percent,0);
  await call('archiveCourse',{params:{course:course.id},status:204});
  await call('showCourse',{params:{course:course.id},status:404});
  persist('Archived curriculum retains enrollment and completion history',s=>s.courses.find(c=>c.id===course.id).deleted_at&&s.lessons.find(l=>l.id===lesson.id).deleted_at&&s.enrollments.length===1&&s.completions.length===1);
 });
 await step('Token listing excludes secrets, owned revocation and current revocation take effect',async()=>{
  const disposable=await issue('admin',['tokens:read','tokens:revoke']);
  const listed=await call('listTokens');assert.ok(listed.data.every(t=>!('token' in t)&&!('access_token' in t)));
  await call('revokeOwnedToken',{params:{token:disposable.token.id},status:204});
  await call('listTokens',{token:disposable.access_token,status:401});
  await call('revokeCurrentToken',{as:'other',status:204});await call('listTokens',{as:'other',status:401});
 });
 await step('Disabling a teacher through the administrator form invalidates existing API access',async()=>{
  await form(admin,'/admin/teachers/'+ids.teacher+'/edit',{_method:'PUT',name:accounts.teacher.name,email:accounts.teacher.email,is_active:'0',password:'',password_confirmation:'',current_password:accounts.admin.password},'/admin/teachers/'+ids.teacher);
  await call('listCourses',{as:'teacher',status:401});
  persist('Teacher deactivation persisted',s=>!s.users.find(u=>u.id===ids.teacher).is_active);
 });
 await step('Token issuance rejects invalid credentials and throttles the sixth attempt',async()=>{
  const body={email:prefix+'-invalid@example.test',password:'Invalid-synthetic-only-2026!',name:'Denied client',abilities:['courses:read'],expires_in_minutes:1};
  for(let i=0;i<5;i++)await call('issueToken',{as:'anonymous',body,status:401});
  await call('issueToken',{as:'anonymous',body,status:429});
 });
 await step('A genuinely elapsed one-minute token is rejected over HTTP',async()=>{
  assert.ok(Number.isFinite(studentExpiresAt));
  const remaining=Math.max(0,studentExpiresAt-Date.now()+1100);
  assert.ok(remaining<61000);
  await new Promise(resolve=>setTimeout(resolve,remaining));
  await call('listCourses',{as:'student',status:401});
  result.expiration_check={issued_minutes:1,waited_ms:Math.round(remaining),status_after_expiration:401};
 });
 await step('All 24 documented operations executed with a successful real HTTP response',async()=>{
  const covered=new Set(result.responses.filter(r=>r.status<300).map(r=>r.operation));assert.deepEqual([...covered].sort(),Object.keys(operations).sort());
  result.operation_coverage={documented:Object.keys(operations).length,successful:covered.size};
 });
 result.outcome='passed';
}catch(e){result.outcome='failed';result.error=clean(e.stack);console.log('FAIL '+clean(e.message));process.exitCode=1;}
finally {
 try {result.cleanup=database(prefix,'cleanup');} catch(e) {result.cleanup={error:clean(e.message)};process.exitCode=1;}
 result.finished_at=new Date().toISOString();
 result.summary={passed:result.cases.filter(x=>x.status==='passed').length,failed:result.cases.filter(x=>x.status==='failed').length,responses:result.responses.length};save();console.log(JSON.stringify(result.summary));
 for(const c of contexts) {
  let timer;try {await Promise.race([c.dispose(),new Promise((_,reject)=>{timer=setTimeout(()=>reject(new Error('HTTP test context disposal exceeded 5 seconds')),5000);})]);}
  catch(e) {result.shutdown_warning=clean(e.message);}finally{clearTimeout(timer);}
 }
 stopServer(server);save();
}
