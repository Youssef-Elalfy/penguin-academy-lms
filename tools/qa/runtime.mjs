import fs from 'node:fs';
import path from 'node:path';
import { parseEnv } from 'node:util';
import { spawn, spawnSync } from 'node:child_process';
import assert from 'node:assert/strict';
export const root = path.resolve(import.meta.dirname, '../..');
export const app = path.join(root, 'app');
export const php = path.join(root, '.runtime/bin/php');
export const base = 'http://127.0.0.1:4174';
// Use only the isolated test database, with normal web CSRF/session middleware enabled.
// Credentials stay in the process environment, never in the evidence.
export const environment = { ...process.env, ...parseEnv(fs.readFileSync(path.join(app, '.env.testing'), 'utf8')),
  APP_ENV:'local', APP_DEBUG:'false', APP_URL:base,
  DB_DATABASE:'penguin_academy_test', DB_USERNAME:'penguin_test',
  SESSION_DRIVER:'database', SESSION_COOKIE:'penguin-m6-qa', CACHE_STORE:'database',
  BCRYPT_ROUNDS:'4', PHP_CLI_SERVER_WORKERS:'4' };
export function phpCommand(args, input, cwd=root) {
  const result=spawnSync(php,args,{cwd,env:environment,input,encoding:'utf8',maxBuffer:16*1024*1024});
  assert.equal(result.status,0,'QA PHP command failed (private runtime log required).');
  return result.stdout;
}
export function database(prefix, operation='inspect') {
  return JSON.parse(phpCommand(['tools/qa/database.php'],JSON.stringify({prefix,operation})));
}
export async function startServer(kind) {
  const folder=path.join(root,'.runtime/m6-'+kind); fs.mkdirSync(folder,{recursive:true,mode:0o700});
  const log=fs.openSync(path.join(folder,'server.log'),'a',0o600);
  const server=spawn(php,['artisan','serve','--host=127.0.0.1','--port=4174','--no-reload'],{cwd:app,env:environment,stdio:['ignore',log,log],detached:true});
  for(let i=0;i<80;i++) {
    try { if((await fetch(base+'/up')).ok) return server; }catch{}
    await new Promise(r=>setTimeout(r,100));
  }
  stopServer(server); throw new Error('QA HTTP server failed to start.');
}
export function stopServer(server) { if(server) {try {process.kill(-server.pid,'SIGTERM');}catch{}} }
