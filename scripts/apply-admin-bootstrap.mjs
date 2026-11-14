import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { resolveAdminPath } from './finalize-build.mjs';

export function validateBootstrapHash(hash) {
  if (typeof hash !== 'string' || !/^\$2[aby]\$[0-9]{2}\$[./A-Za-z0-9]{53}$/.test(hash)) {
    throw new Error('PBP_ADMIN_PASSWORD_HASH must be a valid bcrypt hash');
  }
  return hash;
}

export async function applyAdminBootstrap(env = process.env, request = fetch) {
  if (!env.PBP_ADMIN_PATH || !env.PBP_BOOTSTRAP_TOKEN || !env.PBP_BOOTSTRAP_FILENAME) {
    throw new Error('Admin migration requires production-only environment variables');
  }
  if (!/^setup-[0-9a-f]{48}\.php$/.test(env.PBP_BOOTSTRAP_FILENAME)) {
    throw new Error('Invalid ephemeral migration filename');
  }
  const hash = validateBootstrapHash(env.PBP_ADMIN_PASSWORD_HASH);
  const slug = resolveAdminPath(env);
  const url = 'https://bpreg.tw1.ru/' + slug + '/api/' + env.PBP_BOOTSTRAP_FILENAME;
  let response;
  try {
    response = await request(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: env.PBP_BOOTSTRAP_TOKEN, password_hash: hash }),
      redirect: 'error',
      signal: AbortSignal.timeout(20000),
    });
  } catch {
    // Never print the URL, challenge, hash, or request body to public logs.
    throw new Error('Could not contact protected server-side admin migration.');
  }
  if (!response.ok) throw new Error('Admin migration failed (HTTP ' + response.status + ')');
  let result;
  try { result = await response.json(); } catch { throw new Error('Admin migration returned non-JSON'); }
  if (result?.ok !== true) throw new Error('Admin migration did not confirm success');
  console.log('Admin password rotated; existing contacts and SMTP state preserved.');
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  await applyAdminBootstrap();
}
