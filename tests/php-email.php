<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/dist/api/common.php';
require_once dirname(__DIR__) . '/dist/api/mailer.php';

// This test is executed with normal PHP and with php -n (no ext-intl).
$cases = [
    'noreply@путь-без-преград73.рф' => 'noreply@xn----73-63depjfbu6gfocwq7n.xn--p1ai',
    'noreply@XN----73-63DEPJFBU6GFOCWQ7N.XN--P1AI' => 'noreply@xn----73-63depjfbu6gfocwq7n.xn--p1ai',
    'person@YANDEX.RU' => 'person@yandex.ru',
    'broken-address' => null,
    'a@' => null,
    'a@invalid domain.ru' => null,
    "a@invalid\nbad.ru" => null,
    'имя@пример.рф' => null,
];
foreach ($cases as $input => $expected) {
    $got = normalized_email($input);
    if ($got !== $expected) {
        fwrite(STDERR, "Invalid IDN normalization for " . json_encode($input, JSON_UNESCAPED_UNICODE) .
            ': ' . var_export($got, true) . ' instead of ' . var_export($expected, true) . PHP_EOL);
        exit(1);
    }
}
$human = email_for_display('noreply@xn----73-63depjfbu6gfocwq7n.xn--p1ai');
if ($human !== 'noreply@путь-без-преград73.рф') {
    fwrite(STDERR, "Invalid IDN display: {$human}\n"); exit(1);
}
$state = [
    'smtp' => [
        'host' => 'smtp.timeweb.ru',
        'user' => 'noreply@xn----73-63depjfbu6gfocwq7n.xn--p1ai',
        'password' => 'a-test-password-not-a-real-secret',
    ],
];
$status = smtp_public_status($state);
if (!$status['configured'] || $status['user'] !== $human ||
    array_key_exists('password', $status)) {
    fwrite(STDERR, "SMTP status exposed password or misrepresented user.\n"); exit(1);
}
$errors = [
    'SMTP Error: Could not authenticate.' => 'SMTP_AUTH_FAILED',
    'SMTP connect() failed.' => 'SMTP_CONNECTION_FAILED',
    'certificate verify failed' => 'SMTP_TLS_FAILED',
    'Invalid address: From' => 'SMTP_ADDRESS_INVALID',
];
foreach ($errors as $message => $expected) {
    if (classify_smtp_error($message) !== $expected) {
        fwrite(STDERR, "Unexpected classification for: {$message}\n"); exit(1);
    }
}
echo "IDN and SMTP diagnostics OK; intl extension: " .
    (extension_loaded('intl') ? 'present' : 'absent') . PHP_EOL;
