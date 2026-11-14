import fs from 'node:fs';
import path from 'node:path';

const root = 'site-dist';
const publicRoot = path.join(root, 'public');
const appRoot = path.join(root, 'app');
const slug = process.env.PBP_ADMIN_PATH || 'local-admin-preview-only';
const mustExist = [
  'index.html', '.htaccess', 'admin/index.php', 'login/index.php',
  'api/public.php', 'api/submit.php', 'api/admin.php', 'api/settings.php',
  'api/.htaccess',
  'images/accessible-van.avif', 'images/accessible-passenger.avif',
  'images/accessible-boarding.avif', 'images/put-bez-pregrad/favicon.svg',
  slug + '/index.html', slug + '/api/admin.php', slug + '/api/settings.php',
];
for (const file of mustExist) {
  const target = path.join(publicRoot, file);
  if (!fs.existsSync(target) || fs.statSync(target).size === 0) {
    throw Error('Missing webroot resource: ' + target);
  }
}
for (const filename of ['public.php', 'submit.php', 'common.php', 'spam.php', 'mailer.php']) {
  const file = path.join(appRoot, 'api', filename);
  if (!fs.existsSync(file) || !fs.statSync(file).size) throw Error('Missing backend: ' + file);
}
for (const filename of ['admin.php', 'settings.php']) {
  const file = path.join(appRoot, 'admin-api', filename);
  if (!fs.existsSync(file) || !fs.statSync(file).size) throw Error('Missing admin backend: ' + file);
}
for (const file of ['api/common.php', 'api/spam.php', 'api/mailer.php', 'storage', 'app', '.pbp-private']) {
  if (fs.existsSync(path.join(publicRoot, file))) throw Error('Sensitive file in public/: ' + file);
}
for (const [file, target] of [
  ['api/public.php', '/app/api/public.php'],
  ['api/submit.php', '/app/api/submit.php'],
  [slug + '/api/admin.php', '/app/admin-api/admin.php'],
  [slug + '/api/settings.php', '/app/admin-api/settings.php'],
]) {
  const contents = fs.readFileSync(path.join(publicRoot, file), 'utf8');
  if (!contents.includes(target) || contents.includes('password_verify')) {
    throw Error('Invalid public gateway: ' + file);
  }
}
for (const file of ['index.html', slug + '/index.html']) {
  const html = fs.readFileSync(path.join(publicRoot, file), 'utf8');
  if (!/assets\/[^" ]+\.js/.test(html) || !/assets\/[^" ]+\.css/.test(html)) {
    throw Error('Missing frontend build assets in ' + file);
  }
}
for (const pathPart of ['admin', 'login']) {
  if (fs.existsSync(path.join(publicRoot, pathPart, 'index.html'))) {
    throw Error('Legacy admin frontend exposed');
  }
  if (!fs.readFileSync(path.join(publicRoot, pathPart, 'index.php'), 'utf8').includes('http_response_code(404)')) {
    throw Error('Legacy administration must respond 404');
  }
}
for (const filename of ['admin.php', 'settings.php']) {
  if (!fs.readFileSync(path.join(publicRoot, 'api', filename), 'utf8').includes('http_response_code(404)')) {
    throw Error('Old public administration API must return 404');
  }
}
console.log('Isolated public/ + app/ build verified. No storage data included.');
