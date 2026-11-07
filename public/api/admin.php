<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
start_admin_session();
header('Cache-Control: no-store');
$action = $_GET['action'] ?? 'session';
if ($action === 'session') {
    only_method('GET');
    response_json(['authenticated' => ($_SESSION['admin'] ?? false) === true, 'csrf' => $_SESSION['csrf']]);
}
only_method('POST');
same_origin();
$data = input_json();
validate_csrf($data);
if ($action === 'login') {
    if (!throttle('admin-login', 8, 900)) response_json(['error' => 'Слишком много попыток. Повторите позднее'], 429);
    $password = $data['password'] ?? null;
    if (!is_string($password) || strlen($password) > 1024 ||
        !password_verify($password, current_state()['password_hash'])) {
        response_json(['error' => 'Неверный пароль'], 401);
    }
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    response_json(['ok' => true, 'csrf' => $_SESSION['csrf']]);
}
require_admin();
if ($action === 'logout') {
    $_SESSION = [];
    session_regenerate_id(true);
    session_destroy();
    response_json(['ok' => true]);
}
if ($action === 'settings') {
    $s = $data['settings'] ?? null;
    if (!is_array($s) || array_is_list($s)) response_json(['error' => 'Неверные настройки'], 422);
    $primary = clean_single_line($s['primary_phone'] ?? null, 80);
    $secondary = clean_single_line($s['secondary_phone'] ?? null, 80);
    $contact = clean_single_line($s['contact_email'] ?? null, 250);
    $request = clean_single_line($s['request_email'] ?? null, 250);
    $max = clean_single_line($s['max_url'] ?? null, 600);
    if (!$primary || !$secondary ||
        !preg_match('/^[+\d()\s.-]{6,80}$/', $primary) ||
        !preg_match('/^[+\d()\s.-]{6,80}$/', $secondary) ||
        !filter_var($contact, FILTER_VALIDATE_EMAIL) || !filter_var($request, FILTER_VALIDATE_EMAIL) ||
        !$max || !str_starts_with($max, 'https://max.ru/')) {
        response_json(['error' => 'Проверьте номера телефонов, почты и ссылку MAX'], 422);
    }
    $settings = [
        'primary_phone' => $primary,
        'secondary_phone' => $secondary,
        'contact_email' => $contact,
        'request_email' => $request,
        'max_url' => $max,
    ];
    update_state(static function (array $state) use ($settings): array {
        $state['settings'] = $settings;
        return $state;
    });
    response_json(['ok' => true, 'settings' => $settings]);
}
if ($action === 'password') {
    $current = $data['current_password'] ?? null;
    $next = $data['new_password'] ?? null;
    if (!is_string($current) || !is_string($next) || strlen($next) < 14 ||
        strlen($next) > 128 || !password_verify($current, current_state()['password_hash'])) {
        response_json(['error' => 'Неверный текущий пароль или новый пароль короче 14 символов'], 422);
    }
    $hash = password_hash($next, PASSWORD_DEFAULT);
    update_state(static function (array $state) use ($hash): array {
        $state['password_hash'] = $hash;
        return $state;
    });
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    response_json(['ok' => true, 'csrf' => $_SESSION['csrf']]);
}
response_json(['error' => 'Неизвестная операция'], 404);
