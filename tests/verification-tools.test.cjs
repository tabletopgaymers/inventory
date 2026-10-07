'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const {receipt,verify,plan}=require('../scripts/candidate.cjs');
const {supervise}=require('../scripts/verify.cjs');
const root=path.resolve(__dirname,'..');
const privateRoot=path.join(root,'_private/tool-fixtures');
fs.mkdirSync(privateRoot,{recursive:true});
function syntheticCheckout() {
  const directory=fs.mkdtempSync(path.join(privateRoot,'checkout-'));
  execFileSync('git',['clone','--shared','--no-checkout',root,directory],{stdio:'pipe'});
  fs.mkdirSync(path.join(directory,'scripts'),{recursive:true});
  for(const file of ['candidate.cjs','verification.php','verify.php','check.php']) fs.copyFileSync(path.join(root,'scripts',file),path.join(directory,'scripts',file));
  fs.writeFileSync(path.join(directory,'.gitignore'),'/_private/\n.env.testing\n');
  fs.mkdirSync(path.join(directory,'public'));
  fs.writeFileSync(path.join(directory,'public/fixture.txt'),'ordinary fixture');
  execFileSync('git',['-C',directory,'add','.gitignore','scripts','public/fixture.txt'],{stdio:'pipe'});
  return directory;
}
test('real full dispatch reaches dependency refusal without database gates or redeclaration',async()=>{
  const directory=syntheticCheckout();
  const output=path.join(privateRoot,'full-entry-'+Date.now()+'.log');
  const result=await supervise(process.env.VERIFICATION_PHP,['scripts/verify.php','--full'],{cwd:directory,output,timeoutMs:10000});
  assert.equal(result.exit_code,1);
  const summary=fs.readFileSync(output,'utf8');
  assert.ok(summary.includes('FAIL: dependencies'));
  assert.ok(!summary.includes('Cannot redeclare'));
  const reports=fs.readdirSync(path.join(directory,'_private/verification'));
  assert.equal(reports.length,1);
  const reportDir=path.join(directory,'_private/verification',reports[0]);
  const detail=JSON.parse(fs.readFileSync(path.join(reportDir,'result.json'),'utf8'));
  assert.equal(detail.stage,'dependencies');
  assert.ok(fs.readFileSync(path.join(reportDir,'failure.log'),'utf8').includes('Dependencies missing'));
});
test('actual receipts distinguish ordinary deletion from linked ancestors/junctions',()=>{
  const directory=syntheticCheckout();
  assert.equal(receipt(directory).files.find(f=>f.path==='public/fixture.txt').deleted,undefined);
  fs.unlinkSync(path.join(directory,'public/fixture.txt'));
  assert.equal(receipt(directory).files.find(f=>f.path==='public/fixture.txt').deleted,true);
  fs.rmdirSync(path.join(directory,'public'));
  const outside=fs.mkdtempSync(path.join(privateRoot,'outside-'));
  fs.writeFileSync(path.join(outside,'fixture.txt'),'outside fixture');
  fs.symlinkSync(outside,path.join(directory,'public'),process.platform==='win32'?'junction':'dir');
  try {assert.throws(()=>receipt(directory),/Linked candidate component|escapes checkout/);} finally {fs.unlinkSync(path.join(directory,'public'));}
});
test('actual receipts reject direct and dangling leaf symlinks when supported',t=>{
  const directory=syntheticCheckout();
  const leaf=path.join(directory,'public/fixture.txt');
  fs.unlinkSync(leaf);
  const target=path.join(privateRoot,'leaf-target-'+Date.now()+'.txt');
  fs.writeFileSync(target,'outside fixture');
  try {fs.symlinkSync(target,leaf,'file');} catch(error) {
    if(['EPERM','EACCES','ENOTSUP'].includes(error.code)) {t.skip('File symlinks unavailable on this platform; junction case separately tested');return;}
    throw error;
  }
  try {assert.throws(()=>receipt(directory),/Linked candidate component/);} finally {fs.unlinkSync(leaf);}
  fs.symlinkSync(target+'-missing',leaf,'file');
  try {assert.throws(()=>receipt(directory),/Linked candidate component/);} finally {fs.unlinkSync(leaf);}
});
test('Windows junction leaves, including dangling leaves, are refused',t=>{
  if(process.platform!=='win32') {t.skip('Windows-specific junction; POSIX leaf symlinks tested separately');return;}
  const directory=syntheticCheckout();
  const leaf=path.join(directory,'public/fixture.txt');
  fs.unlinkSync(leaf);
  const target=fs.mkdtempSync(path.join(privateRoot,'junction-leaf-'));
  fs.symlinkSync(target,leaf,'junction');
  try {assert.throws(()=>receipt(directory),/Linked candidate component/);} finally {fs.unlinkSync(leaf);}
  fs.symlinkSync(target+'-missing',leaf,'junction');
  try {assert.throws(()=>receipt(directory),/Linked candidate component/);} finally {fs.unlinkSync(leaf);}
});
test('completion rejects controlled mid-run source/config drift with a private cause',async()=>{
  for(const mode of ['source-drift','config-drift']) {
    const directory=syntheticCheckout();
    const target=path.join(directory,mode==='source-drift'?'public/fixture.txt':'.env.testing');
    if(mode==='config-drift') fs.writeFileSync(target,'synthetic configuration');
    const output=path.join(privateRoot,mode+'-'+Date.now()+'.log');
    const result=await supervise(process.env.VERIFICATION_PHP,['tests/verification-fixture.php',mode,target,directory],{cwd:root,output,timeoutMs:20000});
    assert.equal(result.exit_code,125);
    const match=fs.readFileSync(output,'utf8').match(/Private receipt: (.+)\/result.json/);
    assert.ok(match);
    const detail=JSON.parse(fs.readFileSync(match[1]+'/result.json','utf8'));
    assert.equal(detail.status,'blocked'); assert.equal(detail.stages[0].status,'passed');
    assert.equal(detail.completion_identity.status,'mismatch-or-unavailable');
    const cause=fs.readFileSync(match[1]+'/completion-failure.log','utf8');
    assert.ok(cause.includes(mode==='source-drift'?'Candidate identity changed':'Configuration or dependency identity changed'));
  }
});
test('schema and date changes select existing mocks and display assertions',()=>{
  for(const p of ['app/Support/BaselineProbe.php','app/Support/DisplayDates.php']) {
    const result=plan([p]);
    for(const expected of ['tests/Feature/RequestSchemaTest.php','tests/Feature/HostedBaselineTest.php','tests/Feature/DisplayDatesTest.php','tests/Feature/InventoryWorkflowTest.php']) assert.ok(result.php_tests.includes(expected));
    assert.equal(result.full_gate_required_before_push,true);
  }
});
test('invoice source and its focused test retain invoice Node coverage',()=>{
  assert.ok(plan(['public/purchase-invoice.js']).node_tests.includes('tests/purchase-invoice.test.cjs'));
  const focused=plan(['tests/purchase-invoice.test.cjs']);
  assert.equal(focused.scope,'focused');
  assert.deepEqual(focused.node_tests,['tests/purchase-invoice.test.cjs']);
  assert.equal(focused.full_gate_required_before_push,true);
});

test('unknown changes broaden; empty and unsafe input rejected',()=>{
  assert.equal(plan(['new-area/new-file.php']).scope,'broad');
  assert.ok(plan(['new-area/new-file.php']).php_tests.length>10);
  assert.throws(()=>plan([])); assert.throws(()=>plan(['../escape']));
  assert.throws(()=>plan(['.env.testing']));
});
test('frozen receipt rejects candidate drift and allowlist/index surprises',()=>{
  const frozen=receipt();
  const gitList=args=>execFileSync('git',['-C',root,...args]).toString().split('\0').filter(Boolean);
  const allowlist=[...new Set([...gitList(['diff','--name-only','-z','HEAD']),...gitList(['ls-files','--others','--exclude-standard','-z'])])];
  const staged=gitList(['diff','--cached','--name-only','-z']);
  const spec={receipt:frozen,allowlist,exclusions:[],expected_staged:staged};
  assert.equal(verify(spec).status,'verified');
  if(allowlist.length) assert.throws(()=>verify({...spec,allowlist:[]}));
  assert.throws(()=>verify({...spec,allowlist:['../unsafe']}));
  assert.throws(()=>verify({...spec,expected_staged:['scripts/__fixture-unstaged__.php']}));
  assert.throws(()=>verify({...spec,allowlist:['scripts/check.php'],exclusions:['scripts/check.php']}));
  assert.throws(()=>verify({...spec,receipt:{...frozen,head:'wrong'}}));
});
test('PHP stage runner privately diagnoses pass, failure and timeout with exact exit codes',async()=>{
  const php=process.env.VERIFICATION_PHP;
  assert.ok(php,'Set VERIFICATION_PHP to the installed PHP executable');
  for(const [mode,code,status] of [['pass',0,'passed'],['fail',7,'failed'],['timeout',124,'timeout']]) {
    const marker=path.join(privateRoot,'php-'+mode+'-'+Date.now()+'.pid');
    const output=path.join(privateRoot,'php-'+mode+'-'+Date.now()+'.log');
    const result=await supervise(php,['tests/verification-fixture.php',mode,marker],{cwd:root,output,timeoutMs:10000});
    assert.equal(result.exit_code,code);
    const summary=fs.readFileSync(output,'utf8');
    assert.ok(!summary.includes('PRIVATE_FIXTURE_VALUE'));
    const match=summary.match(/Private receipt: (.+)\/result.json/);
    assert.ok(match,summary);
    const detail=JSON.parse(fs.readFileSync(match[1]+'/result.json','utf8'));
    assert.equal(detail.stages[0].status,status); assert.equal(detail.stages[0].exit_code,code);
    assert.ok(detail.candidate.raw_manifest_sha256);
    if(mode==='timeout') assert.throws(()=>process.kill(Number(fs.readFileSync(marker,'utf8')),0));
    else assert.ok(fs.readFileSync(match[1]+'/stage-1.log','utf8').includes('PRIVATE_FIXTURE_VALUE'));
  }
});
test('PHP interrupted receipt survives and supervised child is stopped',async()=>{
  const controller=new AbortController();
  const marker=path.join(privateRoot,'php-interrupt-'+Date.now()+'.pid');
  const before=new Set(fs.readdirSync(path.join(root,'_private/verification')));
  const work=supervise(process.env.VERIFICATION_PHP,['tests/verification-fixture.php','interrupt',marker],{cwd:root,output:path.join(privateRoot,'php-interrupt-'+Date.now()+'.log'),signal:controller.signal,timeoutMs:10000});
  for(let i=0;i<200&&!fs.existsSync(marker);i++) await new Promise(r=>setTimeout(r,25));
  assert.ok(fs.existsSync(marker));
  try {
    const busy=await supervise(process.env.VERIFICATION_PHP,['tests/verification-fixture.php','pass'],{cwd:root,output:path.join(privateRoot,'busy-'+Date.now()+'.log'),timeoutMs:10000});
    assert.equal(busy.exit_code,125,'A second runner cannot acquire the active lease');
  } finally {controller.abort();}
  const result=await work; assert.equal(result.exit_code,130);
  assert.throws(()=>process.kill(Number(fs.readFileSync(marker,'utf8')),0));
  const created=fs.readdirSync(path.join(root,'_private/verification')).filter(p=>!before.has(p)&&!p.startsWith('blocked-')&&fs.existsSync(path.join(root,'_private/verification',p,'result.json')));
  assert.equal(created.length,1);
  const detail=JSON.parse(fs.readFileSync(path.join(root,'_private/verification',created[0],'result.json'),'utf8'));
  assert.equal(detail.status,'interrupted'); assert.equal(detail.stages[0].status,'interrupted');
});
test('a missing required stage cannot report a passed run',async()=>{
  const output=path.join(privateRoot,'incomplete-'+Date.now()+'.log');
  const result=await supervise(process.env.VERIFICATION_PHP,['tests/verification-fixture.php','incomplete'],{cwd:root,output,timeoutMs:10000});
  assert.equal(result.exit_code,125);
  const match=fs.readFileSync(output,'utf8').match(/Private receipt: (.+)\/result.json/);
  assert.ok(match);
  const detail=JSON.parse(fs.readFileSync(match[1]+'/result.json','utf8'));
  assert.equal(detail.status,'blocked'); assert.equal(detail.required_stages,2); assert.equal(detail.stages.length,1);
});
test('supervision preserves failure exit and keeps private output out of summary',async()=>{
  const output=path.join(privateRoot,'fail-'+Date.now()+'.log');
  const result=await supervise(process.execPath,['-e','console.error("PRIVATE_FIXTURE_VALUE"); process.exit(7)'],{cwd:root,output});
  assert.equal(result.status,'failed'); assert.equal(result.exit_code,7);
  assert.ok(fs.readFileSync(output,'utf8').includes('PRIVATE_FIXTURE_VALUE'));
  assert.ok(!JSON.stringify(result).includes('PRIVATE_FIXTURE_VALUE'));
});
test('supervision passes and distinguishes timeout',async()=>{
  const pass=await supervise(process.execPath,['-e','process.exit(0)'],{cwd:root,output:path.join(privateRoot,'pass-'+Date.now()+'.log')});
  assert.equal(pass.exit_code,0);
  const timeout=await supervise(process.execPath,['-e','setInterval(()=>{},1000)'],{cwd:root,output:path.join(privateRoot,'timeout-'+Date.now()+'.log'),timeoutMs:150});
  assert.equal(timeout.status,'timeout'); assert.equal(timeout.exit_code,124);
});
test('interruption stops owned nested child tree',async()=>{
  const controller=new AbortController();
  const marker=path.join(privateRoot,'child-'+Date.now()+'.json');
  const code=`const {spawn}=require('node:child_process'); const fs=require('node:fs'); const c=spawn(process.execPath,['-e','setInterval(()=>{},1000)'],{stdio:'ignore'}); fs.writeFileSync(${JSON.stringify(marker)},JSON.stringify({parent:process.pid,child:c.pid})); setInterval(()=>{},1000);`;
  const work=supervise(process.execPath,['-e',code],{cwd:root,output:path.join(privateRoot,'interrupt-'+Date.now()+'.log'),signal:controller.signal,timeoutMs:10000});
  for(let i=0;i<100&&!fs.existsSync(marker);i++) await new Promise(r=>setTimeout(r,25));
  assert.ok(fs.existsSync(marker));
  const ids=JSON.parse(fs.readFileSync(marker,'utf8')); controller.abort();
  const result=await work;
  assert.equal(result.status,'interrupted'); assert.equal(result.exit_code,130);
  for(const pid of Object.values(ids)) assert.throws(()=>process.kill(pid,0));
});
