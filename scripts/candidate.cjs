/* Exact source evidence and conservative selection. No approval or reuse claims. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const {execFileSync} = require('node:child_process');
const root = path.resolve(__dirname, '..');
const sha = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const git = (args, directory=root) => execFileSync('git', ['-C', directory, ...args], {maxBuffer: 16 * 1024 * 1024});
const lines = buffer => buffer.toString().split('\0').filter(Boolean).sort();
function safe(p) {
  if (!p || /[\r\n\x00":\\]/.test(p) || p.startsWith('/') || p.split('/').some(x => x === '..' || x === '.' || !x) || /^(_private|_secrets|_data|vendor|node_modules)\//.test(p) || /^\.env(?:\.|$)/.test(p) && !['.env.example', '.env.testing.example'].includes(p)) throw Error('Unsafe candidate path');
  return p;
}
function regularFile(p, directory) {
  safe(p);
  const base=fs.realpathSync(directory);
  let current=base;
  const components=p.split('/');
  for(let i=0;i<components.length;i++) {
    current=path.join(current,components[i]);
    let stat;
    try {stat=fs.lstatSync(current);} catch(error) {
      if(error.code==='ENOENT') return false;
      throw error;
    }
    if(stat.isSymbolicLink()) throw Error('Linked candidate component');
    const resolved=fs.realpathSync(current);
    const relative=path.relative(base,resolved);
    const equal=(a,b)=>process.platform==='win32'?a.toLowerCase()===b.toLowerCase():a===b;
    if(relative==='..'||relative.startsWith('..'+path.sep)||path.isAbsolute(relative)||!equal(resolved,current)) throw Error('Candidate component escapes checkout');
    if(i<components.length-1?!stat.isDirectory():!stat.isFile()) throw Error('Nonregular candidate component');
  }
  return true;
}
function receipt(directory=root) {
  const tracked = lines(git(['ls-files', '-z'],directory));
  // Refuse replaced indexed parents before Git scans untracked directories.
  for(const p of tracked) regularFile(p,directory);
  const untracked = lines(git(['ls-files', '--others', '--exclude-standard', '-z'],directory));
  const paths = [...new Set([...tracked, ...untracked])].sort();
  const present = paths.filter(p => regularFile(p,directory));
  const blobs = present.length ? execFileSync('git',['-C',directory,'hash-object','--stdin-paths'],{input:present.join('\n')+'\n',maxBuffer:16*1024*1024}).toString().trim().split(/\r?\n/) : [];
  const normalized = new Map(present.map((p,i)=>[p,blobs[i]]));
  if(blobs.length!==present.length || blobs.some(blob=>!/^([a-f0-9]{40}|[a-f0-9]{64})$/.test(blob))) throw Error('Normalized identity incomplete');
  const files = paths.map(p => {
    safe(p);
    const full = path.join(directory, p);
    if (!regularFile(p,directory)) return {path:p, deleted:true};
    const bytes = fs.readFileSync(full);
    const blob = normalized.get(p);
    return {path:p, raw_sha256:sha(bytes), normalized_blob:blob};
  });
  return {version:1, head:git(['rev-parse','HEAD'],directory).toString().trim(), local_origin_main:git(['rev-parse','--verify','refs/remotes/origin/main'],directory).toString().trim(), upstream_freshness:'not asserted; no network fetch', files, raw_manifest_sha256:sha(JSON.stringify(files)), normalized_manifest_sha256:sha(JSON.stringify(files.map(f=>({path:f.path,blob:f.normalized_blob??null}))))};
}
function verify(spec) {
  // A frozen receipt plus explicit change allowlist/exclusions and expected index.
  const current = receipt();
  if (JSON.stringify(current) !== JSON.stringify(spec.receipt)) throw Error('Candidate drift');
  const allow = new Set(spec.allowlist.map(safe));
  const exclusions = new Set(spec.exclusions.map(safe));
  if ([...allow].some(p=>exclusions.has(p))) throw Error('Allowlist/exclusion overlap');
  const changed = lines(git(['diff','--name-only','-z','HEAD']));
  const unknown = lines(git(['ls-files','--others','--exclude-standard','-z']));
  for (const p of [...changed,...unknown]) if (!allow.has(p) || exclusions.has(p)) throw Error('Changed path outside allowlist');
  const staged = lines(git(['diff','--cached','--name-only','-z']));
  if (JSON.stringify(staged) !== JSON.stringify([...spec.expected_staged].map(safe).sort())) throw Error('Staged path mismatch');
  for (const p of staged) {
    const f = current.files.find(f=>f.path===p);
    if (f?.deleted) {
      try { git(['rev-parse', ':'+p]); } catch { continue; }
      throw Error('Staged deletion mismatch');
    }
    if (!f || git(['rev-parse', ':'+p]).toString().trim() !== f.normalized_blob) throw Error('Index blob mismatch');
  }
  return {status:'verified', raw_manifest_sha256:current.raw_manifest_sha256, normalized_manifest_sha256:current.normalized_manifest_sha256, review:'not asserted', acceptance:'not asserted'};
}
function plan(paths) {
  if (!paths.length) throw Error('Explicit changed paths required');
  const all = fs.readdirSync(path.join(root,'tests/Feature')).filter(p=>p.endsWith('Test.php')).map(p=>'tests/Feature/'+p).sort();
  const node = ['tests/inventory-search-feedback.test.cjs','tests/request-presentation.test.cjs','tests/purchase-invoice.test.cjs','tests/verification-tools.test.cjs'];
  const selected = new Set();
  let broad = false;
  const reasons = [];
  for (const p of paths) {
    safe(p);
    // Shared schema/auth/date/cost/posting helpers are intentionally broad; this
    // includes all existing schema mocks and display expectations.
    if (/^(app\/Support\/|app\/Http\/Middleware\/|database\/|bootstrap\/|config\/|routes\/|scripts\/)/.test(p) || ['composer.json','composer.lock','phpunit.xml','tests/TestCase.php'].includes(p)) { broad=true; reasons.push('shared-critical:'+p); }
    else if (/^tests\/Feature\/[A-Za-z0-9]+Test\.php$/.test(p) && all.includes(p)) selected.add(p);
    else if (/^tests\/.+\.test\.cjs$/.test(p) && node.includes(p)) selected.add(p);
    else if (/^(public\/|resources\/views\/|app\/Http\/Controllers\/|app\/Models\/)/.test(p)) { broad=true; reasons.push('presentation/domain-dependencies:'+p); }
    else { broad=true; reasons.push('unknown:'+p); }
  }
  return {version:1, scope:broad?'broad':'focused', paths, php_tests:broad?all:[...selected].filter(p=>p.endsWith('.php')).sort(), node_tests:broad?node:[...selected].filter(p=>p.endsWith('.cjs')).sort(), reasons, full_gate_required_before_push:true};
}
module.exports = {receipt,verify,plan};
if (require.main === module) {
  try {
    const [mode,...args] = process.argv.slice(2);
    let result;
    if (mode==='receipt') result=receipt();
    else if (mode==='plan') result=plan(args);
    else if (mode==='verify' && args.length===1) result=verify(JSON.parse(fs.readFileSync(args[0],'utf8')));
    else throw Error('Invalid mode');
    process.stdout.write(JSON.stringify(result,null,2)+'\n');
  } catch { process.stderr.write('BLOCKED: candidate identity, policy or arguments invalid.\n'); process.exitCode=1; }
}
