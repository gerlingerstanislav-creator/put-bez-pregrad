import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import crypto from 'node:crypto';
import { prepareAdminBootstrap } from '../scripts/create-admin-bootstrap.mjs';
import { validateBootstrapHash, applyAdminBootstrap } from '../scripts/apply-admin-bootstrap.mjs';

const slug = 'site-secure-path-example-0123456789';
const validHash = '$2y$12$' + 'A'.repeat(53);

test('rejects absent, weak or malformed password hashes before deployment', () => {
  assert.equal(validateBootstrapHash(validHash), validHash);
  for (const bad of ['', undefined, '$2y$05$' + 'A'.repeat(53), '$2y$12$notlong']) {
    assert.throws(() => validateBootstrapHash(bad));
  }
});

test('ephemeral migration embeds only an expiring token digest, never a password', () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pbp-migration-'));
  const githubEnv = path.join(dir, 'github-env');
  const apiDir = path.join(dir, slug, 'api');
  fs.mkdirSync(apiDir, { recursive: true });
  try {
    const { target, filename, token } = prepareAdminBootstrap(dir, {
      PBP_ADMIN_PATH: slug, GITHUB_ENV: githubEnv,
    }, Date.UTC(2026, 10, 14));
    const content = fs.readFileSync(target, 'utf8');
    assert.match(filename, /^setup-[0-9a-f]{48}\.php$/);
    assert.ok(content.includes(crypto.createHash('sha256').update(token).digest('hex')));
    assert.ok(!content.includes(token));
    assert.ok(!content.includes(validHash));
    assert.ok(!content.includes('__BOOTSTRAP_EXPIRES_AT__'));
    assert.ok(!content.includes('__BOOTSTRAP_TOKEN_DIGEST__'));
    assert.ok(fs.readFileSync(githubEnv, 'utf8').includes('PBP_BOOTSTRAP_FILENAME=' + filename));
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
});

test('migration request uses HTTPS, POST, and sends the hash in JSON body only', async () => {
  let captured;
  const fakeFetch = async (url, options) => {
    captured = { url, options };
    return { ok: true, status: 200, json: async () => ({ ok: true }) };
  };
  await applyAdminBootstrap({
    PBP_ADMIN_PATH: slug,
    PBP_BOOTSTRAP_FILENAME: 'setup-' + 'a'.repeat(48) + '.php',
    PBP_BOOTSTRAP_TOKEN: 'b'.repeat(64),
    PBP_ADMIN_PASSWORD_HASH: validHash,
  }, fakeFetch);
  assert.equal(captured.options.method, 'POST');
  assert.ok(captured.url.startsWith('https://'));
  assert.ok(!captured.url.includes(validHash));
  assert.deepEqual(JSON.parse(captured.options.body), {
    token: 'b'.repeat(64), password_hash: validHash,
  });
});
