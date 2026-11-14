import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { finalizeBuild, resolveAdminPath } from '../scripts/finalize-build.mjs';

test('private admin path is validated and never publicly predictable', () => {
  assert.equal(resolveAdminPath({ PBP_ADMIN_PATH: 'private-admin-test-1234567890' }), 'private-admin-test-1234567890');
  assert.throws(() => resolveAdminPath({ PBP_ADMIN_PATH: '../admin' }));
  assert.throws(() => resolveAdminPath({ PBP_ADMIN_PATH: 'admin' }));
});

test('production packaging relocates admin entry without exposing /admin/', () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pbp-dist-'));
  try {
    fs.mkdirSync(path.join(dir, 'admin'));
    fs.writeFileSync(path.join(dir, 'admin', 'index.html'), '<html>ADMIN_INTERNAL_ENTRY</html>');
    fs.writeFileSync(path.join(dir, 'admin', 'index.php'), '<?php http_response_code(404); exit;');
    const slug = 'secure-preview-abcdef-123456789';
    finalizeBuild(dir, { PBP_ADMIN_PATH: slug });
    assert.match(fs.readFileSync(path.join(dir, slug, 'index.html'), 'utf8'), /ADMIN_INTERNAL_ENTRY/);
    assert.ok(!fs.existsSync(path.join(dir, 'admin', 'index.html')));
    for (const file of ['admin.php', 'settings.php']) {
      const gateway = fs.readFileSync(path.join(dir, slug, 'api', file), 'utf8');
      assert.match(gateway, /private\\/admin-api\\//);
      assert.ok(fs.statSync(path.join(dir + '-private', 'admin-api', file)).size > 100);
      assert.ok(!gateway.includes('password_verify'));
    }
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
    fs.rmSync(dir + '-private', { recursive: true, force: true });
  }
});
