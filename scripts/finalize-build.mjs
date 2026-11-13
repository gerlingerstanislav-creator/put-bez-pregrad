import fs from 'node:fs';
import path from 'node:path';

// A private entry URL is generated only while packaging, never committed to Git.
// CI may use a fixed local test path; deployment MUST set PBP_ADMIN_PATH secret.
export function resolveAdminPath(env = process.env) {
  const slug = env.PBP_ADMIN_PATH || 'local-admin-preview';
  if (!/^[a-zA-Z0-9][a-zA-Z0-9-]{19,79}$/.test(slug)
    || ['admin', 'api', 'login', 'assets', 'images'].includes(slug.toLowerCase())) {
    throw new Error('PBP_ADMIN_PATH must be 20–80 alphanumeric/hyphen characters');
  }
  return slug;
}

export function finalizeBuild(dir = 'dist', env = process.env) {
  const slug = resolveAdminPath(env);
  const privateRoot = path.join(dir, slug);
  fs.mkdirSync(path.join(privateRoot, 'api'), { recursive: true });

  // Vite emits the admin entry under /admin/; move it before deployment.
  fs.renameSync(path.join(dir, 'admin', 'index.html'), path.join(privateRoot, 'index.html'));
  for (const name of ['admin.php', 'settings.php']) {
    fs.copyFileSync(path.join('server', 'admin-api', name), path.join(privateRoot, 'api', name));
  }

  // The legacy URL must not contain JS, redirects, or a working login.
  fs.writeFileSync(path.join(dir, 'admin', 'index.html'),
    '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>404</title></head><body><h1>404</h1><p>Страница не найдена.</p></body></html>\n');
}

if (process.argv[1] && path.resolve(process.argv[1]) === path.resolve(new URL(import.meta.url).pathname)) {
  finalizeBuild();
}
