import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { finalizeBuild, resolveAdminPath } from '../scripts/finalize-build.mjs';

test('admin path is validated', () => {
  assert.equal(resolveAdminPath({ PBP_ADMIN_PATH: 'private-admin-test-1234567890' }),
    'private-admin-test-1234567890');
  for (const input of ['../admin', 'admin', 'storage', 'app']) {
    assert.throws(() => resolveAdminPath({ PBP_ADMIN_PATH: input }));
  }
});

test('Laravel-style build isolates backend, state and old administration paths', () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pbp-build-'));
  const viteDir = path.join(dir, 'vite');
  const releaseDir = path.join(dir, 'site');
  const slug = 'test-private-admin-123456789';
  try {
    fs.mkdirSync(path.join(viteDir, 'admin'), { recursive: true });
    fs.mkdirSync(path.join(viteDir, 'api'), { recursive: true });
    fs.writeFileSync(path.join(viteDir, 'admin', 'index.html'), 'ADMIN_ENTRY');
    fs.writeFileSync(path.join(viteDir, 'admin', 'index.php'), '<?php http_response_code(404);');
    for (const filename of ['common.php', 'mailer.php', 'spam.php', 'public.php', 'submit.php']) {
      fs.writeFileSync(path.join(viteDir, 'api', filename), '<?php // ' + filename);
    }
    finalizeBuild(viteDir, { PBP_ADMIN_PATH: slug }, releaseDir);
    const web = path.join(releaseDir, 'public');
    const backend = path.join(releaseDir, 'app');
    assert.equal(fs.readFileSync(path.join(web, slug, 'index.html'), 'utf8'), 'ADMIN_ENTRY');
    assert.equal(fs.existsSync(path.join(web, 'admin', 'index.html')), false);
    assert.equal(fs.existsSync(path.join(web, 'api', 'common.php')), false);
    assert.equal(fs.existsSync(path.join(web, 'storage')), false);
    for (const [file, handler] of [
      ['api/public.php', 'api/public.php'],
      ['api/submit.php', 'api/submit.php'],
      [slug + '/api/admin.php', 'admin-api/admin.php'],
      [slug + '/api/settings.php', 'admin-api/settings.php'],
    ]) {
      const gateway = fs.readFileSync(path.join(web, file), 'utf8');
      assert.ok(gateway.includes('/app/' + handler), file);
      assert.ok(fs.statSync(path.join(backend, handler)).size > 0);
    }
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
});
