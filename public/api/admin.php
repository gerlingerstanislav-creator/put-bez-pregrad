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
    $primary = format_ru_phone($s['primary_phone'] ?? null);
    $secondary = format_ru_phone($s['secondary_phone'] ?? null);
    $contact = normalized_email($s['contact_email'] ?? null);
    $request = normalized_email($s['request_email'] ?? null);
    $max = clean_single_line($s['max_url'] ?? null, 600);
    if (!$primary || !$secondary || !$contact || !$request ||
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
if ($action === 'smtp') {
    if (!throttle('admin-smtp-config', 15, 600)) response_json(['error' => 'Слишком много попыток'], 429);
    $host = $data['host'] ?? null;
    $user = normalized_email($data['user'] ?? null);
    $pass = $data['password'] ?? null;
    if (!is_string($host) || !in_array($host, ['smtp.timeweb.ru', 'smtp.yandex.ru'], true) ||
        !$user || !is_string($pass) || strlen($pass) > 512) {
        response_json(['error' => 'Проверьте сервис, почтовый адрес и пароль'], 422);
    }
    $state = current_state();
    $current = is_array($state['smtp'] ?? null) ? $state['smtp'] : [];
    if ($pass === '' && ($user !== ($current['user'] ?? null) || $host !== ($current['host'] ?? null))) {
        response_json(['error' => 'При изменении SMTP-логина или сервиса укажите пароль'], 422);
    }
    $password = $pass !== '' ? $pass : ($current['password'] ?? '');
    if (!is_string($password) || $password === '') response_json(['error' => 'Укажите пароль почтового ящика'], 422);
    $state = update_state(static function (array $state) use ($host, $user, $password): array {
        $state['smtp'] = ['host' => $host, 'user' => $user, 'password' => $password];
        return $state;
    });
    response_json(['ok' => true, 'smtp' => smtp_public_status($state)]);
}
if ($action === 'test-email') {
    if (!throttle('admin-email-test', 4, 600)) response_json(['error' => 'Слишком много тестов. Повторите позднее'], 429);
    $state = current_state();
    if (!smtp_public_status($state)['configured']) response_json(['error' => 'Сначала сохраните настройки SMTP'], 422);
    $recipient = normalized_email($state['settings']['request_email'] ?? null);
    if (!$recipient) response_json(['error' => 'Укажите корректную почту получения заявок'], 422);
    require_once __DIR__ . '/mailer.php';
    try {
        send_site_message($state['smtp'], $recipient,
            'Тест почты — Путь без преград',
            "Тестовое сообщение отправлено из панели администрирования сайта.\nЕсли письмо пришло, SMTP настроен корректно.");
    } catch (Throwable $e) {
        // Diagnostic is visible only to an authenticated admin.
        $kind = $e->getMessage();
        if (str_starts_with($kind, 'SMTP_SEND_FAILED:')) {
            response_json(['error' => 'SMTP отклонил письмо. Проверьте логин, пароль приложения и настройки сервиса. Код: SMTP_SEND_FAILED'], 502);
        }
        response_json(['error' => 'Не удалось отправить тестовое письмо: ' . substr($kind, 0, 80)], 502);
    }
    response_json(['ok' => true, 'message' => 'SMTP принял тестовое письмо. Проверьте папку входящих и спам']);
}
if ($action === 'test-native-mail') {
    // The standard Timeweb Exim transport can be tested without SMTP credentials.
    if (!throttle('admin-native-mail-test', 3, 900)) {
        response_json(['error' => 'Не больше 3 проверок за 15 минут'], 429);
    }
    $state = current_state();
    $recipient = normalized_email($state['settings']['request_email'] ?? null);
    if (!$recipient) response_json(['error' => 'Сначала укажите почту получения заявок'], 422);

    $testId = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $subject = 'Timeweb PHP mail test ' . $testId;
    $body = "This is a test of Timeweb local PHP mail transport (Exim).\n" .
        "Site: Put Bez Pregrad\nTest ID: {$testId}\n";
    // Minimal invocation, matching Timeweb's documented PHP mail() test.
    $result = @mail($recipient, $subject, $body);
    if (!$result) {
        error_log('PBP native PHP mail test failed: ' . $testId);
        response_json([
            'error' => 'PHP mail() вернул FALSE. Локальный Exim не принял письмо. ' .
                'Проверьте ограничения аккаунта Timeweb или отправьте обращение в поддержку. ' .
                'Код теста: ' . $testId,
            'test_id' => $testId,
            'accepted' => false,
        ], 502);
    }
    response_json([
        'ok' => true,
        'accepted' => true,
        'test_id' => $testId,
        'message' => 'PHP mail() вернул TRUE — Exim принял сообщение. ' .
            'Проверьте входящие и спам на ' . $recipient .
            '. Тема содержит код ' . $testId . '. Доставка адресату ещё не подтверждена.',
    ]);
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
