import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// A private entry URL is generated only while packaging, never committed to Git.
// CI may use a fixed local test path; deployment MUST set PBP_ADMIN_PATH secret.
export function resolveAdminPath(env = process.env) {
  const slug = env.PBP_ADMIN_PATH || 'local-admin-preview-only';
  if (!/^[a-zA-Z0-9][a-zA-Z0-9-]{19,79}$/.test(slug)
    || ['admin', 'api', 'login', 'assets', 'images'].includes(slug.toLowerCase())) {
    throw new Error('PBP_ADMIN_PATH must be 20–80 alphanumeric/hyphen characters');
  }
  return slug;
}

export function finalizeBuild(dir = 'dist', env = process.env, privateOutput = dir === 'dist' ? 'private-dist' : dir + '-private') {
  const slug = resolveAdminPath(env);
  const privateRoot = path.join(dir, slug);
  fs.mkdirSync(path.join(privateRoot, 'api'), { recursive: true });

  // Vite emits the admin entry under /admin/; move it before deployment.
  fs.renameSync(path.join(dir, 'admin', 'index.html'), path.join(privateRoot, 'index.html'));
  // Public URLs are tiny entrypoints. Real admin handlers are deployed outside webroot.
  fs.mkdirSync(path.join(privateOutput, 'admin-api'), { recursive: true });
  for (const name of ['admin.php', 'settings.php']) {
    fs.copyFileSync(path.join('server', 'admin-api', name), path.join(privateOutput, 'admin-api', name));
    const gateway = '<?php\\ndeclare(strict_types=1);\\nrequire_once dirname(__DIR__, 3) . \'/private/admin-api/' + name + '\';\\n';
    fs.writeFileSync(path.join(privateRoot, 'api', name), gateway.replaceAll('\\n', '\n'));
  }

  // PHP returns an actual 404 for /admin/, including when the web server
  // ignores Apache .htaccess rules (common on shared Nginx hosting).
  // An index.php 404 stub is copied from public/admin by Vite.
  if (!fs.existsSync(path.join(dir, 'admin', 'index.php'))) {
    throw new Error('Missing legacy admin 404 PHP stub');
  }
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  finalizeBuild();
}
