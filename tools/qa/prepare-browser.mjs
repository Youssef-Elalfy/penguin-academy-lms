import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { brotliDecompressSync } from 'node:zlib';
import { spawnSync } from 'node:child_process';
import assert from 'node:assert/strict';
const require=createRequire(import.meta.url);
const root=path.resolve(import.meta.dirname,'../..');
const output=path.join(root,'.runtime/m6-browser-linux');
const pkg=path.resolve(path.dirname(require.resolve('@sparticuz/chromium')), '..');
fs.mkdirSync(output,{recursive:true,mode:0o700});
for(const name of ['chromium','fonts.tar','swiftshader.tar','al2023.tar']) {
 const data=brotliDecompressSync(fs.readFileSync(path.join(pkg,'bin',name+'.br')));
 if(name==='chromium') fs.writeFileSync(path.join(output,'chromium'),data,{mode:0o700});
 else { const r=spawnSync('tar',['--no-same-owner','--no-same-permissions','-xf','-','-C',output],{input:data}); assert.equal(r.status,0); }
}
console.log('Prepared local Linux Chromium from the pinned npm package; no browser download or ownership changes.');
