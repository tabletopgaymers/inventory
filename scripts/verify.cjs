'use strict';
const {spawn, execFile} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

// Supervise only our direct child tree. This also catches user interruption on
// Windows, where externally terminating PHP need not invoke shutdown functions.
function supervise(command, args, {cwd, output, signal, timeoutMs = 0}) {
  return new Promise((resolve, reject) => {
    const start = performance.now();
    const log = fs.openSync(output, 'wx', 0o600);
    const child = spawn(command, args, {cwd, detached:process.platform !== 'win32', windowsHide:true, stdio:['ignore',log,log]});
    let stopping = null;
    let timer;
    let forceTimer;
    let cleanup = Promise.resolve();
    let cleanupFailed = false;
    const stop = reason => {
      if (stopping || child.exitCode !== null || !child.pid) return;
      stopping = reason;
      if (process.platform === 'win32') {
        // /T addresses descendants of this owned PID, never process-name matches.
        cleanup = new Promise(done => execFile('taskkill', ['/PID',String(child.pid),'/T','/F'], {windowsHide:true}, error => {
          if (error) {
            cleanupFailed=true;
            if (child.exitCode === null) child.kill('SIGKILL');
          }
          done();
        }));
      } else {
        try { process.kill(-child.pid, 'SIGTERM'); } catch {}
        forceTimer=setTimeout(()=>{try {process.kill(-child.pid,'SIGKILL');} catch {}},1000);
      }
    };
    const abort = () => stop('interrupted');
    signal?.addEventListener('abort', abort, {once:true});
    if (signal?.aborted) abort();
    if (timeoutMs) timer=setTimeout(()=>stop('timeout'), timeoutMs);
    child.once('error', error => { clearTimeout(timer); signal?.removeEventListener('abort',abort); fs.closeSync(log); reject(error); });
    child.once('close', async (code, childSignal) => {
      clearTimeout(timer);
      await cleanup;
      clearTimeout(forceTimer);
      signal?.removeEventListener('abort',abort);
      fs.closeSync(log);
      resolve({status:cleanupFailed?'cleanup-error':stopping ?? (code===0?'passed':'failed'), exit_code:cleanupFailed?125:stopping==='interrupted'?130:stopping==='timeout'?124:code??125, cleanup_failed:cleanupFailed, child_exit_code:code, child_signal:childSignal, seconds:Number(((performance.now()-start)/1000).toFixed(3))});
    });
  });
}
module.exports={supervise};
if (require.main === module) {
  (async()=>{
    const root=path.resolve(__dirname,'..');
    const args=process.argv.slice(2);
    if (!['--full','--focused'].includes(args[0])) throw Error('Explicit verification mode required');
    const reports=path.join(root,'_private/verification');
    if(fs.existsSync(reports)&&fs.readdirSync(reports,{withFileTypes:true}).filter(entry=>entry.isDirectory()).length>=100) throw Error('Private report retention capacity reached');
    const dir=path.join(root,'_private/verification/supervised-'+Date.now()+'-'+crypto.randomBytes(5).toString('hex'));
    fs.mkdirSync(dir,{recursive:true,mode:0o700});
    const controller=new AbortController();
    const interrupt=()=>controller.abort();
    process.on('SIGINT',interrupt); process.on('SIGTERM',interrupt);
    fs.writeFileSync(path.join(dir,'result.json'),JSON.stringify({status:'interrupted',exit_code:null}),{mode:0o600});
    const result=await supervise(process.env.VERIFICATION_PHP || 'php', ['scripts/verify.php',...args],{cwd:root,output:path.join(dir,'console.log'),signal:controller.signal});
    fs.writeFileSync(path.join(dir,'result.json'),JSON.stringify(result,null,2)+'\n',{mode:0o600});
    console.log(`${result.status.toUpperCase()}: verification (${result.seconds}s; exit ${result.exit_code}). Private receipt: ${dir}/result.json`);
    process.exitCode=result.exit_code;
  })().catch(()=>{console.error('BLOCKED: verification supervision prerequisites invalid.');process.exitCode=125;});
}
