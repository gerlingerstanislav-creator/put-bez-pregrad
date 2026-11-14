<?php
declare(strict_types=1);

/**
 * One-time deployment-only migration, packaged under a random filename.
 * Both placeholders are replaced by the runner before FTP upload.
 * No password hash, SMTP credential, or bearer token is stored in this file.
 */
require_once __DIR__ . '/../../api/common.php';
require_https();
only_method('POST');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$expiresAt = __BOOTSTRAP_EXPIRES_AT__;
$tokenDigest = '__BOOTSTRAP_TOKEN_DIGEST__';
if (time() > $expiresAt) response_json(['error' => 'Migration expired'], 410);

$data = input_json();
$token = $data['token'] ?? null;
$hash = $data['password_hash'] ?? null;
if (!is_string($token) || !hash_equals($tokenDigest, hash('sha256', $token))) {
    response_json(['error' => 'Forbidden'], 403);
}
if (!is_string($hash) ||
    !preg_match('/^\$2[aby]\$(?:1[2-9]|2[0-9]|3[01])\$[\.\/A-Za-z0-9]{53}$/D', $hash) ||
    password_get_info($hash)['algoName'] !== 'bcrypt') {
    response_json(['error' => 'Invalid password hash'], 422);
}

// Fresh site: create protected state.php with defaults. Existing contacts and SMTP
// credentials are preserved; this handler never writes secrets into the build.

// Preserves SMTP credentials, contacts and any unrelated server-side state.
update_state(static function (array $state) use ($hash): array {
    $state['password_hash'] = $hash;
    $state['auth_generation'] = bin2hex(random_bytes(16));
    return $state;
});
response_json(['ok' => true]);
