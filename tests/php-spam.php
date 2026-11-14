<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/site-dist/app/api/spam.php';

$challenge = issue_form_challenge();
if (strlen($challenge) !== 64 || form_challenge_is_valid($challenge)) {
    fwrite(STDERR, "A fresh challenge must not be accepted immediately.\n");
    exit(1);
}
sleep(FORM_CHALLENGE_MIN_AGE);
if (!form_challenge_is_valid($challenge)) {
    fwrite(STDERR, "An aged, authentic challenge must be accepted.\n");
    exit(1);
}
$bad = substr($challenge, 0, -1) . ($challenge[-1] === 'A' ? 'B' : 'A');
if (form_challenge_is_valid($bad) || form_challenge_is_valid('') || form_challenge_is_valid(null)) {
    fwrite(STDERR, "A tampered or missing challenge must be rejected.\n");
    exit(1);
}
$expiredPayload = pack('N', time() - FORM_CHALLENGE_MAX_AGE - 5) . random_bytes(12);
$expiredToken = rtrim(strtr(base64_encode(
    $expiredPayload . hash_hmac('sha256', $expiredPayload, form_challenge_secret(), true)
), '+/', '-_'), '=');
if (form_challenge_is_valid($expiredToken)) {
    fwrite(STDERR, "Expired challenges must be rejected.\n");
    exit(1);
}
echo "Anti-spam challenge tests passed.\n";
