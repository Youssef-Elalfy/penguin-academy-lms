import fs from 'node:fs';
import path from 'node:path';
import { randomBytes } from 'node:crypto';
import { spawn, spawnSync } from 'node:child_process';
import { performance } from 'node:perf_hooks';
import assert from 'node:assert/strict';
import { chromium as playwright } from 'playwright';
import chromium from '@sparticuz/chromium';
import AxeBuilder from '@axe-core/playwright';
import { environment } from './runtime.mjs';

const root = path.resolve(import.meta.dirname, '../..');
const app = path.join(root, 'app');
const evidence = path.join(root, 'evidence/m6/browser');
const privateDir = path.join(root, '.runtime/m6-browser-run');
fs.mkdirSync(evidence, { recursive: true });
fs.mkdirSync(privateDir, { recursive: true, mode: 0o700 });
const base = 'http://127.0.0.1:4174';
const prefix = 'm6-' + randomBytes(6).toString('hex');
const password = () => 'Qa9!' + randomBytes(24).toString('base64url');
const accounts = Object.fromEntries(['admin', 'teacher', 'other', 'student'].map(role => [role, {
  name: 'QA ' + role[0].toUpperCase() + role.slice(1),
  email: prefix + '-' + role + '@example.test', password: password(),
}]));
const changedPassword = password();
const secrets = [...Object.values(accounts).map(x => x.password), changedPassword];

const php = path.join(root, '.runtime/bin/php');
const result = { run: prefix, started_at: new Date().toISOString(), base_url: base, database: 'penguin_academy_test',
  browser: null, cases: [], layouts: [], accessibility: [], screenshots: [], console_errors: [], persistence: [] };
let browser, server;
const contexts = [];
const clean = value => {
  let text = String(value);
  for (const secret of secrets) text = text.split(secret).join('[REDACTED]');
  return text.replace(/\b\d+\|[A-Za-z0-9]{40,}\b/g, '[REDACTED_TOKEN]');
};
const save = () => fs.writeFileSync(path.join(evidence, 'results.json'), JSON.stringify(result, null, 2) + '\n');
function database(operation = 'inspect') {
  const command = spawnSync(php, ['tools/qa/database.php'], { cwd: root, env: environment,
    input: JSON.stringify({ operation, prefix }), encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 });
  if (command.status !== 0) throw new Error(clean(command.stderr || command.stdout));
  return JSON.parse(command.stdout);
}
function persist(label, predicate) {
  const state = database();
  assert.ok(predicate(state), 'MySQL assertion: ' + label);
  result.persistence.push({ label, status: 'passed', course_rows: state.courses.length, enrollment_rows: state.enrollments.length, completion_rows: state.completions.length });
  return state;
}
async function step(name, fn) {
  const start = performance.now();
  try { await fn(); result.cases.push({ name, status: 'passed', duration_ms: Math.round(performance.now() - start) }); }
  catch (error) { result.cases.push({ name, status: 'failed', error: clean(error.stack), duration_ms: Math.round(performance.now() - start) }); save(); throw error; }
  save(); console.log('PASS ' + name);
}
async function pageFor(role) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
  contexts.push(context);
  const page = await context.newPage();
  page.on('dialog', dialog => dialog.accept());
  page.on('pageerror', error => result.console_errors.push({ role, message: clean(error.message) }));
  page.setDefaultTimeout(10000);
  return page;
}
async function go(page, route, status = 200) {
  const response = await page.goto(base + route);
  assert.equal(response.status(), status, route + ' response');
  await page.waitForLoadState('networkidle');
}
async function submit(page, name) {
  await page.getByRole('button', { name, exact: true }).click();
  await page.waitForLoadState('networkidle');
}
async function login(page, role, expected) {
  await go(page, '/login');
  if (role === 'student') {
    assert.equal(await page.locator('#email').evaluate(e => document.activeElement === e), true);
    await page.keyboard.type(accounts[role].email);
    await page.keyboard.press('Tab');
    await page.keyboard.type(accounts[role].password);
    await page.keyboard.press('Tab'); await page.keyboard.press('Tab');
    assert.equal(await page.getByRole('button',{name:/Sign in/}).evaluate(e => document.activeElement === e), true);
    await page.keyboard.press('Enter'); await page.waitForURL(base + expected); await page.waitForLoadState('networkidle');
  } else {
    await page.getByLabel('Email address', { exact: true }).fill(accounts[role].email);
    await page.getByLabel('Password', { exact: true }).fill(accounts[role].password);
    await submit(page, /Sign in/);
  }
  assert.equal(new URL(page.url()).pathname, expected);
}
async function logout(page) {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await submit(page, /Sign out/);
  assert.equal(new URL(page.url()).pathname, '/');
}
async function createCourse(page, suffix, title, teacherId = null) {
  await go(page, '/courses/create');
  await page.getByLabel('Course title', { exact: true }).fill(title);
  await page.getByLabel('Course reference', { exact: true }).fill(prefix + '-' + suffix);
  await page.getByLabel('Short introduction').fill('A practical synthetic learning course used for final quality assurance.');
  await page.getByLabel('Course description', { exact: true }).fill('Learn with clear lessons, track progress, and return to your course.\nAll data in this course is disposable test data.');
  if (teacherId !== null) await page.getByLabel('Assigned teacher', { exact: true }).selectOption(String(teacherId));
  await submit(page, 'Create draft course');
  const match = new URL(page.url()).pathname.match(/^\/courses\/(\d+)$/);
  assert.ok(match, 'Course creation redirected to its persisted detail');
  return Number(match[1]);
}
async function createLesson(page, course, title, content) {
  await go(page, '/courses/' + course + '/lessons/create');
  await page.getByLabel('Lesson title', { exact: true }).fill(title);
  await page.getByLabel('Lesson content', { exact: true }).fill(content);
  await submit(page, 'Create draft lesson');
  return database().lessons.find(x => x.course_id === course && x.title === title).id;
}
async function layout(page, role, route, width, screenshot = false) {
  await page.setViewportSize({ width, height: width === 375 ? 812 : 1000 });
  await go(page, route);
  const measurement = await page.evaluate(() => ({
    viewport: window.innerWidth, document: document.documentElement.scrollWidth,
    horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
    h1: document.querySelectorAll('main h1').length,
    unlabeled: [...document.querySelectorAll('main input:not([type=hidden]):not([type=submit]),main select,main textarea')]
      .filter(e => !e.labels?.length && !e.getAttribute('aria-label') && !e.getAttribute('aria-labelledby')).map(e => e.name),
    brokenImages: [...document.images].filter(e => e.complete && e.naturalWidth === 0).length,
  }));
  result.layouts.push({ role, route, width, ...measurement, status: measurement.horizontalOverflow ? 'failed' : 'passed' });
  if (screenshot) {
    const name = role + '-' + width + '.png';
    await page.screenshot({ path: path.join(evidence, name), fullPage: width !== 1440 });
    if (width === 1440) {
      await page.screenshot({ path: path.join(evidence, role + '-1440-viewport.png'), fullPage: false });
      result.navigation = result.navigation || [];
      result.navigation.push({role, elements: await page.locator('.sidebar-brand, .academy-nav a, .sidebar-logout').evaluateAll(elements => elements.map(e => ({ text: e.textContent.trim(), visibility: getComputedStyle(e).visibility, opacity: getComputedStyle(e).opacity, rect: e.getBoundingClientRect().toJSON() }))) });
      assert.ok(await page.locator('.sidebar-brand').isVisible());
      assert.ok(await page.getByRole('navigation', {name: 'Main navigation'}).isVisible());
    }
    result.screenshots.push(name);
  }
  assert.equal(measurement.viewport, width);
  assert.equal(measurement.h1, 1, 'One primary heading within main');
  assert.equal(measurement.horizontalOverflow, false, role + ' overflow at ' + width + ' on ' + route);
  assert.deepEqual(measurement.unlabeled, []);
  assert.equal(measurement.brokenImages, 0);
  save();
}
async function scan(page, role, route) {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await go(page, route);
  const audit = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
  result.accessibility.push({ role, route, passes: audit.passes.length, incomplete: audit.incomplete.map(x => ({ id: x.id, nodes: x.nodes.map(n => ({target: n.target, failureSummary: n.failureSummary})) })),
    violations: audit.violations.map(x => ({ id: x.id, impact: x.impact, description: x.description,
      nodes: x.nodes.map(n => ({ target: n.target, failureSummary: n.failureSummary })) })) });
  save();
}
try {
  const provision = spawnSync(php, ['artisan', 'academy:admin', '--name=' + accounts.admin.name, '--email=' + accounts.admin.email, '--password-stdin', '--no-interaction'],
    { cwd: app, env: environment, input: accounts.admin.password + '\n', encoding: 'utf8' });
  assert.equal(provision.status, 0, clean(provision.stdout + provision.stderr));
  persist('Administrator provisioned through existing secure command', s => s.users.some(u => u.role === 'admin'));
  const serverLog = fs.openSync(path.join(privateDir, 'server.log'), 'a', 0o600);
  server = spawn(php, ['artisan', 'serve', '--host=127.0.0.1', '--port=4174', '--no-reload'], { cwd: app, env: environment, stdio: ['ignore', serverLog, serverLog], detached: true });
  for (let attempt = 0; attempt < 80; attempt++) {
    try { if ((await fetch(base + '/up')).ok) break; } catch {}
    if (attempt === 79) throw new Error('Local test server did not become ready.');
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  const executable = process.env.QA_CHROMIUM_EXECUTABLE || path.join(root, '.runtime/m6-browser-linux/chromium');
  browser = await playwright.launch({ executablePath: executable, args: chromium.args, headless: true,
    env: { ...process.env, FONTCONFIG_PATH: path.join(path.dirname(executable), 'fonts'), LD_LIBRARY_PATH: path.dirname(executable) } });
  result.browser = { engine: await browser.version(), playwright: '1.62.1', implementation: 'Real local Chromium with fresh isolated contexts; all sign-in through Fortify forms.' };
  const guest = await pageFor('guest');
  await step('Public authentication forms are responsive and accessible', async () => {
    for (const width of [375,768,1440]) for (const route of ['/login','/register']) await layout(guest, 'guest', route, width);
    for (const route of ['/login','/register']) await scan(guest, 'guest', route);
  });
  const admin = await pageFor('admin');
  const teacher = await pageFor('teacher');
  const student = await pageFor('student');
  let teacherId, otherId, mainCourse, otherCourse, firstLesson, secondLesson, draftLesson, enrollmentId;
  await step('Administrator login and dashboard', async () => {
    await login(admin, 'admin', '/admin/dashboard');
    assert.match(await admin.locator('h1').innerText(), /Academy overview/);
  });
  await step('Real browser session cookies and CSRF rejection', async () => {
    const cookies = await admin.context().cookies();
    const session = cookies.find(c => c.name === 'penguin-m6-qa');
    assert.ok(session?.httpOnly); assert.equal(session.sameSite, 'Lax');
    const response = await admin.request.post(base + '/logout', {form:{}});
    assert.equal(response.status(),419);
    assert.ok(response.headers()['content-security-policy']);
    await go(admin, '/admin/dashboard');
    result.session_controls = {http_only:true, same_site:'Lax', csrf_missing_token_status:419, local_http_secure_cookie:false};
  });
  await step('Administrator creates two teacher accounts through protected forms', async () => {
    for (const role of ['teacher', 'other']) {
      await go(admin, '/admin/teachers/create');
      await admin.getByLabel('Full name', { exact: true }).fill(accounts[role].name);
      await admin.getByLabel('Email address', { exact: true }).fill(accounts[role].email);
      await admin.getByLabel('Temporary password', { exact: true }).fill(accounts[role].password);
      await admin.getByLabel('Confirm temporary password', { exact: true }).fill(accounts[role].password);
      await admin.getByLabel('Your administrator password', { exact: true }).fill(accounts.admin.password);
      await submit(admin, 'Create teacher account');
      assert.match(new URL(admin.url()).pathname, /^\/admin\/teachers\/\d+\/edit$/);
    }
    const s = persist('Teachers have Teacher role and mandatory password change', s => s.users.filter(u => u.role === 'teacher' && u.must_change_password).length === 2);
    teacherId = s.users.find(u => u.email === accounts.teacher.email).id;
    otherId = s.users.find(u => u.email === accounts.other.email).id;
  });
  await step('Administrator course creation, edit, teacher assignment and publication', async () => {
    mainCourse = await createCourse(admin, 'learning', 'Practical Web Foundations', otherId);
    await admin.getByLabel('Assign a teacher', { exact: true }).selectOption(String(teacherId));
    await submit(admin, 'Save assignment');
    await go(admin, '/courses/' + mainCourse + '/edit');
    await admin.getByLabel('Course title', { exact: true }).fill('Web Foundations — Learn by Doing');
    await submit(admin, 'Save course');
    await submit(admin, 'Publish course');
    persist('Course title, teacher and publication persisted', s => s.courses.some(c => c.id === mainCourse && c.teacher_id === teacherId && c.published_at && c.title.includes('Learn by Doing')));
    otherCourse = await createCourse(admin, 'private', 'Other Teacher Private Course', otherId);
  });
  await step('Teacher login enforces password change and redirects to teacher dashboard', async () => {
    await login(teacher, 'teacher', '/account/password');
    await teacher.getByLabel('Current password', { exact: true }).fill(accounts.teacher.password);
    await teacher.getByLabel('New password', { exact: true }).fill(changedPassword);
    await teacher.getByLabel('Confirm new password', { exact: true }).fill(changedPassword);
    await submit(teacher, 'Update password');
    accounts.teacher.password = changedPassword;
    assert.equal(new URL(teacher.url()).pathname, '/teacher/dashboard');
    persist('Mandatory password change cleared after supported form', s => s.users.some(u => u.id === teacherId && !u.must_change_password));
  });
  await step('Teacher assignment scope and cross-course/admin rejection', async () => {
    await go(teacher, '/courses/' + mainCourse);
    await go(teacher, '/courses/' + otherCourse, 403);
    await go(teacher, '/courses/' + otherCourse + '/edit', 403);
    await go(teacher, '/courses/' + otherCourse + '/enrollments', 403);
    await go(teacher, '/admin/dashboard', 403);
  });
  await step('Teacher creates and edits own course', async () => {
    const own = await createCourse(teacher, 'teacher-created', 'Teacher Created Draft');
    await go(teacher, '/courses/' + own + '/edit');
    await teacher.getByLabel('Course title', { exact: true }).fill('Teacher Created Draft — Revised');
    await submit(teacher, 'Save course');
    persist('Teacher-created course assigns current teacher', s => s.courses.some(c => c.id === own && c.teacher_id === teacherId && c.title.endsWith('Revised')));
  });
  await step('Teacher creates, edits, publishes and reorders lessons', async () => {
    firstLesson = await createLesson(teacher, mainCourse, 'Build a thoughtful interface', 'Start with clear structure.\nUse semantic HTML and accessible controls.\n<script>window.qaUnsafe = true</script>');
    secondLesson = await createLesson(teacher, mainCourse, 'Connect learning to practice', 'Apply your learning in a small project.\nReview it and reflect on the result.');
    draftLesson = await createLesson(teacher, mainCourse, 'Private draft lesson', 'This lesson is not published.');
    await go(teacher, '/courses/' + mainCourse + '/lessons/' + firstLesson + '/edit');
    await teacher.getByLabel('Lesson title', { exact: true }).fill('Build an accessible interface');
    await submit(teacher, 'Save lesson');
    await go(teacher, '/courses/' + mainCourse);
    await submit(teacher, 'Publish Build an accessible interface');
    await submit(teacher, 'Publish Connect learning to practice');
    await go(teacher, '/courses/' + mainCourse + '/lessons/order');
    await teacher.getByRole('button', { name: 'Move position 2 up', exact: true }).click();
    await submit(teacher, 'Save lesson order');
    persist('Lesson publication and order persist', s => {
      const one = s.lessons.find(l => l.id === firstLesson), two = s.lessons.find(l => l.id === secondLesson);
      return one.is_published && two.is_published && two.position < one.position && !s.lessons.find(l => l.id === draftLesson).is_published;
    });
  });
  await step('Student registration, logout, keyboard-only login and role redirect', async () => {
    await go(student, '/register');
    await student.getByLabel('Full name', { exact: true }).fill(accounts.student.name);
    await student.getByLabel('Email address', { exact: true }).fill(accounts.student.email);
    await student.getByLabel('Password', { exact: true }).fill(accounts.student.password);
    await student.getByLabel('Confirm password', { exact: true }).fill(accounts.student.password);
    await submit(student, /Create account/);
    assert.equal(new URL(student.url()).pathname, '/student/dashboard');
    persist('Registration creates active Student only', s => s.users.some(u => u.email === accounts.student.email && u.role === 'student' && u.is_active));
    await logout(student);
    await login(student, 'student', '/student/dashboard');
  });
  await step('Student catalog and denial before enrollment or for staff/draft content', async () => {
    await go(student, '/courses');
    assert.ok(await student.getByRole('link', { name: 'Web Foundations — Learn by Doing', exact: true }).isVisible());
    assert.equal(await student.getByText('Other Teacher Private Course', { exact: true }).count(), 0);
    await go(student, '/courses/create', 403);
    await go(student, '/admin/dashboard', 403);
    await go(student, '/courses/' + otherCourse, 403);
    await go(student, '/courses/' + mainCourse + '/lessons/' + firstLesson, 403);
  });
  await step('Student enrolls and reads published lesson with escaped content', async () => {
    await go(student, '/courses/' + mainCourse);
    await submit(student, 'Enroll in course');
    const s = persist('One active persisted enrollment', s => s.enrollments.filter(e => e.course_id === mainCourse && !e.withdrawn_at).length === 1);
    enrollmentId = s.enrollments.find(e => e.course_id === mainCourse).id;
    await go(student, '/courses/' + mainCourse + '/lessons/' + firstLesson);
    assert.ok((await student.locator('.reading-text').innerText()).includes('<script>'));
    assert.equal(await student.evaluate(() => typeof window.qaUnsafe), 'undefined');
    await go(student, '/courses/' + mainCourse + '/lessons/' + draftLesson, 403);
  });
  await step('Completion, 50 percent progress and persistence after refresh', async () => {
    await go(student, '/courses/' + mainCourse + '/lessons/' + firstLesson);
    await submit(student, 'Mark complete');
    assert.equal(await student.locator('progress').getAttribute('value'), '50');
    persist('Completion row persisted', s => s.completions.filter(c => c.enrollment_id === enrollmentId && c.lesson_id === firstLesson).length === 1);
    await student.reload();
    assert.equal(await student.locator('progress').getAttribute('value'), '50');
    assert.ok(await student.getByRole('button', { name: 'Undo completion', exact: true }).isVisible());
  });
  await step('Undo completion and complete again', async () => {
    await submit(student, 'Undo completion');
    assert.equal(await student.locator('progress').getAttribute('value'), '0');
    persist('Undo removes only current completion', s => s.completions.length === 0);
    await submit(student, 'Mark complete');
    persist('Re-completion creates one unique row', s => s.completions.length === 1);
  });
  await step('Withdrawal blocks lessons; re-enrollment retains progress and enrollment identity', async () => {
    await go(student, '/courses/' + mainCourse);
    await submit(student, 'Withdraw from course');
    persist('Withdrawn enrollment and historical completion retained', s => s.enrollments.some(e => e.id === enrollmentId && e.withdrawn_at) && s.completions.length === 1);
    await go(student, '/courses/' + mainCourse + '/lessons/' + firstLesson, 403);
    await go(student, '/courses/' + mainCourse);
    await submit(student, 'Re-enroll in course');
    assert.equal(await student.locator('progress').getAttribute('value'), '50');
    persist('Original enrollment reactivated with history', s => s.enrollments.length === 1 && s.enrollments[0].id === enrollmentId && !s.enrollments[0].withdrawn_at && s.completions.length === 1);
  });
  await step('Continue learning opens the next incomplete lesson', async () => {
    await go(student, '/student/dashboard');
    await student.getByRole('link', {name:'Continue learning Web Foundations — Learn by Doing'}).click();
    await student.waitForLoadState('networkidle');
    assert.equal(new URL(student.url()).pathname, '/courses/' + mainCourse + '/lessons/' + secondLesson);
    await student.getByRole('navigation', {name:'Lesson navigation'}).getByRole('link').click();
    await student.waitForLoadState('networkidle');
    assert.equal(new URL(student.url()).pathname, '/courses/' + mainCourse + '/lessons/' + firstLesson);
  });
  await step('Teacher roster, learner report and course report', async () => {
    await go(teacher, '/courses/' + mainCourse + '/enrollments');
    assert.ok(await teacher.getByText(accounts.student.name, { exact: true }).isVisible());
    assert.equal(await teacher.locator('progress').getAttribute('value'), '50');
    await go(teacher, '/courses/' + mainCourse + '/progress');
    assert.ok(await teacher.getByText(accounts.student.email, { exact: true }).isVisible());
    await go(teacher, '/courses/' + mainCourse + '/enrollments/' + enrollmentId + '/progress');
    assert.match(await teacher.locator('main').innerText(), /50%/);
  });
  await step('Administrator enrollment and progress reports', async () => {
    await go(admin, '/admin/enrollments');
    assert.ok(await admin.getByText(accounts.student.email, { exact: true }).isVisible());
    await go(admin, '/courses/' + mainCourse + '/progress');
    assert.match(await admin.locator('main').innerText(), /50%/);
    await go(admin, '/courses/' + mainCourse + '/enrollments/' + enrollmentId + '/progress');
  });
  await step('Server-side form validation focuses accessible error summary', async () => {
    await go(admin, '/courses/create');
    await admin.getByLabel('Course title', { exact: true }).fill('Duplicate reference example');
    await admin.getByLabel('Course reference', { exact: true }).fill(prefix + '-learning');
    await submit(admin, 'Create draft course');
    assert.equal(await admin.locator('[data-error-summary]').evaluate(e => document.activeElement === e), true);
    assert.match(await admin.locator('[data-error-summary]').innerText(), /taken/i);
  });
  await step('Course pagination, filters, empty and validation states at all required widths', async () => {
    for (let index=1;index<=12;index++) {
      const id = await createCourse(admin, 'pagination-'+index, 'Pagination practice course '+index, teacherId);
      await submit(admin, 'Publish course');
    }
    persist('Additional pagination fixtures persist', s => s.courses.length === 15 && s.courses.filter(c => c.published_at).length === 13);
    for (const width of [375,768,1440]) {
      await layout(admin,'admin','/courses',width);
      await admin.locator('a[rel=next]:visible').click(); await admin.waitForLoadState('networkidle');
      assert.equal(new URL(admin.url()).searchParams.get('page'),'2');
      await layout(student,'student','/courses',width);
      await student.locator('a[rel=next]:visible').click(); await student.waitForLoadState('networkidle');
      assert.equal(new URL(student.url()).searchParams.get('page'),'2');
      await layout(admin,'admin','/courses?q=no-match-for-this-course',width);
      assert.ok(await admin.getByText('Your next course starts here.',{exact:true}).isVisible());
      await layout(student,'student','/courses?q=no-match-for-this-course',width);
      assert.ok(await student.getByText('No courses found.',{exact:true}).isVisible());
      await go(admin,'/courses/create');
      await admin.getByLabel('Course title',{exact:true}).fill('Duplicate course');
      await admin.getByLabel('Course reference',{exact:true}).fill(prefix+'-learning');
      await submit(admin,'Create draft course');
      assert.ok(await admin.locator('[data-error-summary]').isVisible());
      assert.equal(await admin.evaluate(() => document.documentElement.scrollWidth > innerWidth+1), false);
    }
    await scan(admin,'admin','/courses?q=no-match-for-this-course');
    await scan(student,'student','/courses?q=no-match-for-this-course');
    await go(student,'/courses'); await student.getByLabel('Search courses',{exact:true}).fill('Pagination practice course 12');
    await submit(student,'Apply filters');
    assert.equal(await student.locator('.catalog-card').count(),1);
    await student.getByRole('link',{name:'Reset',exact:true}).click(); await student.waitForLoadState('networkidle');
    assert.equal(new URL(student.url()).search,'');
  });
  const pages = {
    admin: ['/admin/dashboard','/admin/teachers','/admin/teachers/create','/courses','/courses/create','/courses/' + mainCourse + '/edit','/admin/enrollments','/courses/' + mainCourse + '/progress'],
    teacher: ['/teacher/dashboard','/courses/' + mainCourse,'/courses/' + mainCourse + '/lessons/create','/courses/' + mainCourse + '/lessons/order','/courses/' + mainCourse + '/enrollments','/courses/' + mainCourse + '/progress','/courses/' + mainCourse + '/enrollments/' + enrollmentId + '/progress'],
    student: ['/student/dashboard','/courses','/courses/' + mainCourse,'/courses/' + mainCourse + '/lessons/' + firstLesson,'/my-courses'],
  };
  for (const [role, page] of [['admin',admin],['teacher',teacher],['student',student]]) {
    await step(role + ' responsive layouts at 375, 768 and 1440 pixels', async () => {
      for (const width of [375,768,1440]) for (const route of pages[role]) await layout(page, role, route, width, route === pages[role][0]);
    });
    await step(role + ' desktop sidebar navigation works', async () => {
      await page.setViewportSize({width:1440, height:1000});
      await go(page, pages[role][0]);
      const overview = page.getByRole('navigation', {name: 'Main navigation'}).getByRole('link',{name:'Overview',exact:false});
      await overview.focus();
      result.focus = result.focus || [];
      result.focus.push({role, color:await overview.evaluate(e=>getComputedStyle(e).outlineColor), width:await overview.evaluate(e=>getComputedStyle(e).outlineWidth)});
      assert.equal(await overview.evaluate(e=>getComputedStyle(e).outlineColor),'rgb(185, 220, 203)');
      await page.getByRole('navigation', {name: 'Main navigation'}).getByRole('link', {name: role === 'student' ? 'Course catalog' : 'Courses', exact:false}).click();
      await page.waitForLoadState('networkidle');
      assert.equal(new URL(page.url()).pathname, '/courses');
      await page.getByRole('navigation', {name:'Main navigation'}).getByRole('link', {name:'Overview', exact:false}).click();
      await page.waitForLoadState('networkidle');
      assert.equal(new URL(page.url()).pathname, pages[role][0]);
    });
    await step(role + ' mobile navigation and keyboard focus', async () => {
      await page.setViewportSize({ width: 375, height: 812 });
      await go(page, pages[role][0]);
      const menu = page.getByRole('button', { name: 'Open navigation', exact: true });
      await menu.focus();
      assert.equal(await menu.evaluate(e => getComputedStyle(e).outlineWidth), '3px');
      await page.keyboard.press('Enter');
      await page.locator('#academy-navigation.show').waitFor();
      assert.equal(await menu.getAttribute('aria-expanded'), 'true');
      await page.keyboard.press('Escape');
      await page.locator('#academy-navigation.show').waitFor({ state: 'hidden' });
      assert.equal(await menu.getAttribute('aria-expanded'), 'false');
      assert.equal(await menu.evaluate(e => document.activeElement === e), true);
      assert.equal(await page.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches), true);
      if (role !== 'student') {
        const table = page.locator('.table-responsive').first();
        assert.ok(await table.evaluate(e=>e.scrollWidth>e.clientWidth));
        await table.focus(); await page.keyboard.press('ArrowRight');
        await page.waitForFunction(()=>document.querySelector('.table-responsive').scrollLeft>0);
        result.table_keyboard_scroll = result.table_keyboard_scroll || [];
        result.table_keyboard_scroll.push({role,passed:true});
      }
    });
    for (const route of pages[role]) await scan(page, role, route);
  }
  await step('Teacher lesson archival preserves completion history and updates eligible progress', async () => {
    await go(teacher, '/courses/' + mainCourse + '/lessons/' + firstLesson);
    await submit(teacher, 'Archive lesson');
    persist('Archived completed lesson retains historical row', s => s.lessons.some(l => l.id === firstLesson && l.deleted_at) && s.completions.length === 1);
    await go(student, '/courses/' + mainCourse);
    assert.equal(await student.locator('progress').getAttribute('value'), '0');
    await go(student, '/courses/' + mainCourse + '/lessons/' + firstLesson, 404);
  });
  await step('Administrator archival preserves learning history and revokes student access', async () => {
    await go(admin, '/courses/' + mainCourse);
    await submit(admin, 'Archive course');
    persist('Archived course preserves enrollment and completion', s => s.courses.some(c => c.id === mainCourse && c.deleted_at) && s.enrollments.some(e => e.id === enrollmentId) && s.completions.length === 1);
    await go(student, '/courses/' + mainCourse, 404);
    await go(student, '/courses/' + mainCourse + '/lessons/' + firstLesson, 404);
  });
  await step('Administrator and teacher logout block dashboard access', async () => {
    await logout(admin); await logout(teacher);
    await admin.goto(base + '/admin/dashboard'); assert.equal(new URL(admin.url()).pathname, '/login');
    await teacher.goto(base + '/teacher/dashboard'); assert.equal(new URL(teacher.url()).pathname, '/login');
  });
  await step('Automated accessibility and browser errors have no outstanding failures', async () => {
    assert.equal(result.accessibility.reduce((n, x) => n + x.violations.length, 0), 0, 'Axe violations');
    assert.deepEqual(result.console_errors, []);
  });
  result.outcome = 'completed';
} catch (error) {
  result.outcome = 'failed';
  result.error = clean(error.stack);
  console.log('FAILED ' + clean(error.message));
  process.exitCode = 1;
} finally {
  for (const context of contexts) await context.close().catch(() => {});
  if (browser) await browser.close().catch(() => {});
  if (server) { try { process.kill(-server.pid, 'SIGTERM'); } catch {} }
  result.finished_at = new Date().toISOString();
  result.summary = { passed: result.cases.filter(x => x.status === 'passed').length, failed: result.cases.filter(x => x.status === 'failed').length,
    layouts: result.layouts.length, accessibility_scans: result.accessibility.length,
    accessibility_violations: result.accessibility.reduce((n,x) => n + x.violations.length, 0) };
  try { result.cleanup = database('cleanup'); } catch(error) { result.cleanup = { error: clean(error.message) }; }
  save();
  console.log(JSON.stringify(result.summary));
}

