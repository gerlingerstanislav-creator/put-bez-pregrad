import fs from 'node:fs';
import path from 'node:path';
const required = [
  'index.html','admin/index.php','login/index.php',
  'api/public.php','api/admin.php','api/submit.php','api/common.php','api/settings.php','api/mailer.php','api/spam.php',
  'admin/.htaccess',
  'api/.htaccess','.htaccess','.pbp-private/.htaccess','.pbp-private/index.php',
  'images/accessible-van.avif','images/accessible-passenger.avif','images/accessible-boarding.avif',
  'images/put-bez-pregrad/favicon.svg','images/put-bez-pregrad/logo.svg','images/put-bez-pregrad/logo-light.svg',
];
const adminPath = process.env.PBP_ADMIN_PATH || 'local-admin-preview-only';
required.push(`${adminPath}/index.html`, `${adminPath}/api/admin.php`, `${adminPath}/api/settings.php`);
for(const item of required){
  const p=path.join('dist',item);
  if(!fs.existsSync(p) || fs.statSync(p).size===0)throw Error('Missing or empty: '+p);
}
for(const p of ['dist/index.html',`dist/${adminPath}/index.html`]){
  const html=fs.readFileSync(p,'utf8');
  if(!/assets\/[^" ]+\.js/.test(html) || !/assets\/[^" ]+\.css/.test(html))throw Error('No compiled JS/CSS in '+p);
}
console.log('Production site + administration and PHP files verified');

for (const legacy of ['admin', 'login']) {
  if (fs.existsSync('dist/' + legacy + '/index.html')) {
    throw Error('Legacy /' + legacy + '/ must not contain a static entry');
  }
  if (!fs.readFileSync('dist/' + legacy + '/index.php', 'utf8').includes('http_response_code(404)')) {
    throw Error('Legacy /' + legacy + '/ must return a real HTTP 404');
  }
}
for (const file of ['admin.php', 'settings.php']) {
  if (!fs.readFileSync('dist/api/' + file, 'utf8').includes('http_response_code(404)')) {
    throw Error('Old /api/' + file + ' must return 404');
  }
}
