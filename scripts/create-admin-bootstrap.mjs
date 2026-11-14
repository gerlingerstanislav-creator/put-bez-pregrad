import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { resolveAdminPath } from './finalize-build.mjs';

/** Create a short-lived authenticated migration file, never committed to git. */
export function prepareAdminBootstrap(dist = 'site-dist/public', env = process.env, now = Date.now()) {
  const slug = resolveAdminPath(env);
  if (!env.PBP_ADMIN_PATH || !env.GITHUB_ENV) {
    throw new Error('Admin bootstrap is allowed only in a production GitHub Actions job');
  }
  const token = crypto.randomBytes(32).toString('hex');
  const filename = 'setup-' + crypto.randomBytes(24).toString('hex') + '.php';
  const digest = crypto.createHash('sha256').update(token).digest('hex');
  const expiresAt = String(Math.floor(now / 1000) + 900);
  const template = fs.readFileSync('server/admin-bootstrap.php', 'utf8');
  const content = template
    .replace('__BOOTSTRAP_EXPIRES_AT__', expiresAt)
    .replace('__BOOTSTRAP_TOKEN_DIGEST__', digest);
  const target = path.join(dist, slug, 'api', filename);
  if (!fs.existsSync(path.dirname(target))) throw new Error('Missing private admin API directory');
  fs.writeFileSync(target, content, { mode: 0o600, flag: 'wx' });
  fs.appendFileSync(env.GITHUB_ENV, 'PBP_BOOTSTRAP_TOKEN=' + token + '\n'
    + 'PBP_BOOTSTRAP_FILENAME=' + filename + '\n');
  // Do not log either the secret token or the URL in a public Actions log.
  process.stdout.write('::add-mask::' + token + '\n');
  process.stdout.write('::add-mask::' + filename + '\n');
  console.log('Ephemeral authenticated admin migration prepared.');
  return { target, filename, token };
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  prepareAdminBootstrap();
}
