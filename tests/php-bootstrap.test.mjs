import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import net from 'node:net';
import { spawn, execFileSync } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';
import { prepareAdminBootstrap } from '../scripts/create-admin-bootstrap.mjs';

async function availablePort() {
  const server = net.createServer();
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  const port = server.address().port;
  await new Promise((resolve) => server.close(resolve));
  return port;
}

test('real PHP migration updates admin hash but preserves existing SMTP and contacts', async () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pbp-php-integration-'));
  const slug = 'integration-private-path-1234567890';
  const site = path.join(dir, 'public');
  const api = path.join(site, 'api');
  const storage = path.join(dir, 'storage');
  const privateApi = path.join(site, slug, 'api');
  fs.mkdirSync(api, { recursive: true });
  fs.mkdirSync(privateApi, { recursive: true });
  fs.mkdirSync(storage, { recursive: true });
  const originalState = {
    password_hash: 'old-unused-test-hash',
    settings: { primary_phone: '+7 (8422) 71-73-03', request_email: 'test@example.com' },
    smtp: { host: 'smtp.timeweb.ru', user: 'test@example.com', password: 'synthetic-not-a-secret' },
  };
  const statePath = path.join(storage, 'state.php');
  const prefix = '<?php http_response_code(404); exit; ?>\n';
  fs.writeFileSync(statePath, prefix + JSON.stringify(originalState));
  fs.mkdirSync(path.join(dir, 'app', 'api'), { recursive: true });
  fs.copyFileSync('public/api/common.php', path.join(dir, 'app', 'api', 'common.php'));
  const githubEnv = path.join(dir, 'github-env');
  const { filename, token } = prepareAdminBootstrap(site, {
    PBP_ADMIN_PATH: slug, GITHUB_ENV: githubEnv,
  });
  const passwordHash = execFileSync('php', ['-r', 'echo password_hash("synthetic password for test", PASSWORD_BCRYPT, ["cost" => 12]);'], { encoding: 'utf8' }).trim();
  const port = await availablePort();
  const child = spawn('php', ['-S', '127.0.0.1:' + port, '-t', site], { stdio: 'ignore' });
  const url = 'http://127.0.0.1:' + port + '/' + slug + '/api/' + filename;
  try {
    let ready = false;
    for (let i = 0; i < 35; i += 1) {
      try { await fetch(url); ready = true; break; } catch { await delay(100); }
    }
    assert.ok(ready, 'PHP test server started');
    const bad = await fetch(url, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: 'wrong', password_hash: passwordHash }),
    });
    assert.equal(bad.status, 403);
    const good = await fetch(url, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token, password_hash: passwordHash }),
    });
    assert.equal(good.status, 200);
    assert.equal((await good.json()).ok, true);
    const state = JSON.parse(fs.readFileSync(statePath, 'utf8').slice(prefix.length));
    assert.equal(state.password_hash, passwordHash);
    assert.deepEqual(state.settings, originalState.settings);
    assert.deepEqual(state.smtp, originalState.smtp);
    assert.match(state.auth_generation, /^[0-9a-f]{32}$/);
  } finally {
    child.kill();
    fs.rmSync(dir, { recursive: true, force: true });
  }
});
