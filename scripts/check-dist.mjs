import fs from 'node:fs';
import path from 'node:path';
const required = [
  'index.html','admin/index.html','portal-5i-qy7a79-uGFTtWdc8ZlOyWwVk88qRJ/index.html',
  'api/public.php','api/admin.php','api/submit.php','api/common.php','api/settings.php','api/mailer.php','api/spam.php',
  'portal-5i-qy7a79-uGFTtWdc8ZlOyWwVk88qRJ/api/admin.php','portal-5i-qy7a79-uGFTtWdc8ZlOyWwVk88qRJ/api/settings.php','admin/.htaccess',
  'api/.htaccess','.htaccess',
  'images/accessible-van.avif','images/accessible-passenger.avif','images/accessible-boarding.avif',
  'images/put-bez-pregrad/favicon.svg','images/put-bez-pregrad/logo.svg','images/put-bez-pregrad/logo-light.svg',
];
for(const item of required){
  const p=path.join('dist',item);
  if(!fs.existsSync(p) || fs.statSync(p).size===0)throw Error('Missing or empty: '+p);
}
for(const p of ['dist/index.html','dist/portal-5i-qy7a79-uGFTtWdc8ZlOyWwVk88qRJ/index.html']){
  const html=fs.readFileSync(p,'utf8');
  if(!/assets\/[^" ]+\.js/.test(html) || !/assets\/[^" ]+\.css/.test(html))throw Error('No compiled JS/CSS in '+p);
}
console.log('Production site + administration and PHP files verified');

const oldAdmin = fs.readFileSync('dist/admin/index.html', 'utf8');
if (oldAdmin.includes('assets/') || oldAdmin.includes('src/admin.js')) {
  throw Error('Old /admin/ must not expose a login interface');
}
for (const forbidden of ['dist/login/index.html', 'dist/admin/index.html']) {
  if (!fs.readFileSync(forbidden, 'utf8').includes('Страница не найдена.')) {
    throw Error('Retired entry must not contain a login page: ' + forbidden);
  }
}
for (const file of ['admin.php', 'settings.php']) {
  if (!fs.readFileSync('dist/api/' + file, 'utf8').includes('http_response_code(404)')) {
    throw Error('Old /api/' + file + ' must return 404');
  }
}
