<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

/**
 * Lightweight anonymous anti-bot proof: the page gets a signed timestamp
 * from the server; a request needs at least three seconds of form lifetime.
 * This complements (does not replace) honeypot, validation and rate limits.
 */
const FORM_CHALLENGE_MIN_AGE = 3;
const FORM_CHALLENGE_MAX_AGE = 1800;

function form_challenge_secret(): string
{
    storage_ready();
    $file = SITE_STORAGE . '/form-challenge.key';
    $handle = fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        response_json(['error' => 'Защита формы временно недоступна'], 503);
    }

    try {
        rewind($handle);
        $key = stream_get_contents($handle);
        if (!is_string($key) || strlen($key) !== 32) {
            $key = random_bytes(32);
            ftruncate($handle, 0);
            rewind($handle);
            if (fwrite($handle, $key) !== 32) {
                response_json(['error' => 'Защита формы временно недоступна'], 503);
            }
            fflush($handle);
            chmod($file, 0600);
        }
        return $key;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function issue_form_challenge(): string
{
    $data = pack('N', time()) . random_bytes(12);
    $signature = hash_hmac('sha256', $data, form_challenge_secret(), true);
    return rtrim(strtr(base64_encode($data . $signature), '+/', '-_'), '=');
}

function form_challenge_is_valid(mixed $challenge): bool
{
    if (!is_string($challenge) || !preg_match('/^[A-Za-z0-9_-]{64}$/', $challenge)) {
        // Signed payload is 48 bytes, encoded as exactly 64 URL-safe chars.
        return false;
    }

    $raw = base64_decode(strtr($challenge, '-_', '+/'), true);
    if (!is_string($raw) || strlen($raw) !== 48) return false;

    $payload = substr($raw, 0, 16);
    $timestamp = unpack('Ntime', substr($payload, 0, 4))['time'];
    $age = time() - $timestamp;
    if ($age < FORM_CHALLENGE_MIN_AGE || $age > FORM_CHALLENGE_MAX_AGE) return false;

    return hash_equals(
        hash_hmac('sha256', $payload, form_challenge_secret(), true),
        substr($raw, 16, 32)
    );
}
