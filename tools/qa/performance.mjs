import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import assert from 'node:assert/strict';
import {randomBytes} from 'node:crypto';
import {performance} from 'node:perf_hooks';
import {root,base,phpCommand,database,startServer,stopServer} from './runtime.mjs';
const prefix='m6-'+randomBytes(6).toString('hex'),password='Qa9!'+randomBytes(24).toString('base64url');
let server;const secrets=[password];
const clean=value=>secrets.reduce((s,v)=>s.split(v).join('[REDACTED]'),String(value));
const result={run:prefix,started_at:new Date().toISOString(),environment:{os:os.type()+' '+os.release(),arch:os.arch(),cpu:os.cpus()[0]?.model,logical_cpus:os.cpus().length,host_memory_bytes:os.totalmem(),server:'PHP artisan serve, 4 worker processes; loopback HTTP; BCRYPT_ROUNDS=4 for disposable fixtures'},measurements:[],http:[]};
const save=()=>fs.writeFileSync(path.join(root,'evidence/m6/performance.json'),JSON.stringify(result,null,2)+'\n');
const command=payload=>JSON.parse(phpCommand(['tools/qa/performance.php'],JSON.stringify({prefix,...payload})));
const metrics=values=>{const sorted=[...values].sort((a,b)=>a-b);return {samples:values.length,min_ms:sorted[0],p50_ms:sorted[Math.ceil(sorted.length*.5)-1],p95_ms:sorted[Math.ceil(sorted.length*.95)-1],max_ms:sorted.at(-1)};};
try {
 result.fixture=command({operation:'seed',password});console.log('Seeded '+JSON.stringify(result.fixture.counts));
 server=await startServer('performance');const tokens={};
 for(const role of ['admin','teacher0','student0']) {
  const abilities=role==='student0'?['courses:read','enrollments:read','progress:read']:['courses:read','enrollments:read','progress:read','reports:read'];
  const response=await fetch(base+'/api/v1/tokens',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify({email:prefix+'-'+role+'@example.test',password,name:'Performance fixture',abilities,expires_in_minutes:15})});
  assert.equal(response.status,201);tokens[role]=(await response.json()).data.access_token;secrets.push(tokens[role]);
 }
 const cases=[['admin','/api/v1/courses?per_page=1'],['admin','/api/v1/courses?per_page=100'],['admin','/api/v1/enrollments?per_page=1'],['admin','/api/v1/enrollments?per_page=100'],['admin','/api/v1/reports/overview?per_page=1'],['admin','/api/v1/reports/overview?per_page=100'],['teacher0','/api/v1/reports/overview?per_page=100'],['student0','/api/v1/enrollments?per_page=100'],['student0','/api/v1/enrollments/'+result.fixture.enrollment_id+'/progress']];
 for(const [role,uri] of cases) {const m=command({operation:'measure',uri,token:tokens[role]});assert.equal(m.status,200,uri);result.measurements.push({role,...m});console.log('Measured '+role+' '+uri+' '+m.query_count+' queries');}
 for(const stem of ['/api/v1/courses','/api/v1/enrollments','/api/v1/reports/overview']) {
  const rows=result.measurements.filter(x=>x.role==='admin'&&x.uri.startsWith(stem+'?'));assert.equal(rows[0].business_read_query_count,rows[1].business_read_query_count,'No N+1 growth: '+stem);
 }
 for(const [role,uri] of [['admin','/api/v1/reports/overview?per_page=20'],['teacher0','/api/v1/reports/overview?per_page=20'],['student0','/api/v1/enrollments?per_page=20']]) {
  async function hit(){const start=performance.now();const r=await fetch(base+uri,{headers:{Authorization:'Bearer '+tokens[role],Accept:'application/json'}});await r.arrayBuffer();return {status:r.status,ms:Math.round((performance.now()-start)*100)/100};}
  const warm=await hit();assert.equal(warm.status,200);
  const sequential=[];for(let i=0;i<20;i++)sequential.push(await hit());
  const concurrent=[];for(let b=0;b<5;b++)concurrent.push(...await Promise.all(Array.from({length:4},hit)));
  assert.ok([...sequential,...concurrent].every(x=>x.status===200));
  result.http.push({role,uri,warmup:warm,sequential:metrics(sequential.map(x=>x.ms)),concurrent_four:metrics(concurrent.map(x=>x.ms)),status_counts:{'200':40},failures:0});save();
 }
 const integrity=JSON.parse(phpCommand(['tools/m3-integrity.php']));assert.equal(Object.values(integrity.violations).reduce((a,b)=>a+b,0),0);
 fs.writeFileSync(path.join(root,'evidence/m6/performance-database-integrity.json'),JSON.stringify(integrity,null,2)+'\n');
 result.outcome='passed';result.integrity_violations=integrity.violations;
}catch(e){result.outcome='failed';result.error=clean(e.stack);console.log('FAIL '+clean(e.message));process.exitCode=1;}
finally{stopServer(server);try{result.cleanup=database(prefix,'cleanup');}catch(e){result.cleanup={error:clean(e.message)};process.exitCode=1;}result.finished_at=new Date().toISOString();save();console.log('Performance run '+result.outcome);}
