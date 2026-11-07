import fs from 'node:fs';
import path from 'node:path';
const required = [
  'index.html','admin/index.html',
  'api/public.php','api/admin.php','api/submit.php','api/common.php','api/settings.php','api/mailer.php',
  'api/.htaccess',
  'images/accessible-van.avif','images/accessible-passenger.avif','images/accessible-boarding.avif',
  'images/put-bez-pregrad/favicon.svg','images/put-bez-pregrad/logo.svg','images/put-bez-pregrad/logo-light.svg',
];
for(const item of required){
  const p=path.join('dist',item);
  if(!fs.existsSync(p) || fs.statSync(p).size===0)throw Error('Missing or empty: '+p);
}
for(const p of ['dist/index.html','dist/admin/index.html']){
  const html=fs.readFileSync(p,'utf8');
  if(!/assets\/[^" ]+\.js/.test(html) || !/assets\/[^" ]+\.css/.test(html))throw Error('No compiled JS/CSS in '+p);
}
console.log('Production site + administration and PHP files verified');
