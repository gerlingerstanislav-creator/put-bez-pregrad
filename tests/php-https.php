<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/api/common.php';

function check(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
}
$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';
unset($_SERVER['HTTP_X_HTTPS']);
check(!request_is_https(), 'HTTP without the Timeweb marker remains HTTP');
$_SERVER['HTTP_X_HTTPS'] = '1';
check(request_is_https(), 'Timeweb TLS proxy marker enables HTTPS');
$_SERVER['HTTP_X_HTTPS'] = '0';
check(!request_is_https(), 'Timeweb HTTP marker does not enable HTTPS');
unset($_SERVER['HTTP_X_HTTPS']);
$_SERVER['HTTPS'] = 'on';
check(request_is_https(), 'direct HTTPS still works');
echo "HTTPS transport signals verified\n";
