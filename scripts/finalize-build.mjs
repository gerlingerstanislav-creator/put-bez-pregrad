import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// The private entry URL is injected only at build time through GitHub Secrets.
export function resolveAdminPath(env = process.env) {
  const slug = env.PBP_ADMIN_PATH || 'local-admin-preview-only';
  if (!/^[a-zA-Z0-9][a-zA-Z0-9-]{19,79}$/.test(slug)
    || ['admin', 'api', 'login', 'assets', 'images', 'app', 'storage'].includes(slug.toLowerCase())) {
    throw new Error('PBP_ADMIN_PATH must be 20–80 safe characters');
  }
  return slug;
}

function gateway(relativeToApp) {
  return "<?php\n"
    + "declare(strict_types=1);\n"
    + "require dirname(__DIR__, " + relativeToApp.depth + ") . '/app/"
    + relativeToApp.file + "';\n";
}

/**
 * Build a Laravel-style package:
 *   site-dist/public/ => the ONLY HTTP document root
 *   site-dist/app/    => non-public PHP backend
 *   <site>/storage/   => persistent data, created separately on server
 *
 * Does not include or create credentials, state.php or any files in storage/.
 * No deployment should put site-dist/ itself into public_html.
 */
export function finalizeBuild(dir = 'dist', env = process.env, output = 'site-dist') {
  const slug = resolveAdminPath(env);
  if (path.resolve(dir) === path.resolve(output)) throw new Error('Output must not be webroot');
  const publicRoot = path.join(output, 'public');
  const appRoot = path.join(output, 'app');
  fs.rmSync(output, { recursive: true, force: true });
  fs.mkdirSync(output, { recursive: true });
  fs.cpSync(dir, publicRoot, { recursive: true });
  fs.mkdirSync(path.join(publicRoot, slug, 'api'), { recursive: true });
  fs.mkdirSync(path.join(appRoot, 'api'), { recursive: true });
  fs.mkdirSync(path.join(appRoot, 'admin-api'), { recursive: true });

  fs.renameSync(path.join(publicRoot, 'admin', 'index.html'),
    path.join(publicRoot, slug, 'index.html'));

  // Never publish server-side application source inside public/.
  for (const filename of ['common.php', 'mailer.php', 'spam.php', 'public.php', 'submit.php']) {
    fs.renameSync(path.join(publicRoot, 'api', filename), path.join(appRoot, 'api', filename));
  }
  for (const filename of ['public.php', 'submit.php']) {
    fs.writeFileSync(path.join(publicRoot, 'api', filename),
      gateway({ depth: 2, file: 'api/' + filename }));
  }
  for (const filename of ['admin.php', 'settings.php']) {
    fs.copyFileSync(path.join('server', 'admin-api', filename),
      path.join(appRoot, 'admin-api', filename));
    fs.writeFileSync(path.join(publicRoot, slug, 'api', filename),
      gateway({ depth: 3, file: 'admin-api/' + filename }));
  }
  // Existing legacy URLs still return 404; no symlinks back into app/.
  if (!fs.existsSync(path.join(publicRoot, 'admin', 'index.php'))) {
    throw new Error('Missing old admin 404 stub');
  }
  return { publicRoot, appRoot, slug };
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  finalizeBuild();
}
