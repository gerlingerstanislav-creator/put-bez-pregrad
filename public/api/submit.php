<?php
declare(strict_types=1);
require_once __DIR__ . '/spam.php';
require_https();
only_method('POST');
same_origin();
$data = input_json();
if (!empty($data['website'])) response_json(['ok' => true]);
$from = clean_single_line($data['departure'] ?? null, 250);
$to = clean_single_line($data['destination'] ?? null, 250);
$phone = clean_single_line($data['contact'] ?? null, 35);
$name = $data['passenger'] ?? '';
$name = $name === '' ? 'Не указано' : clean_single_line($name, 120);
if (!$from || !$to || !$phone || !$name || empty($data['consent'])) {
    response_json(['error' => 'Заполните обязательные поля и согласие'], 422);
}
$formattedPhone = format_ru_phone($phone);
if (!$formattedPhone) response_json(['error' => 'Введите полный номер телефона +7'], 422);
$phone = $formattedPhone;
if (!form_challenge_is_valid($data['challenge'] ?? null)) {
    response_json(['error' => 'Не удалось подтвердить заявку. Подождите несколько секунд и попробуйте снова'], 403);
}
if (!throttle('public-request', 5, 600)
    || !throttle('request-phone-' . hash('sha256', $phone), 3, 3600)) {
    response_json(['error' => 'Слишком много заявок. Позвоните нам по телефону'], 429);
}
$recipient = current_state()['settings']['request_email'] ?? '';
if (!is_string($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    response_json(['error' => 'Отправка временно недоступна. Позвоните нам'], 503);
}
$subject = 'Новая заявка — Путь без преград';
$body = "Новая заявка на перевозку\n\n" .
    "Откуда: {$from}\nКуда: {$to}\nИмя: {$name}\nТелефон: {$phone}\n";
$state = current_state();
if (!smtp_public_status($state)['configured']) {
    error_log('Landing mail failed: SMTP not configured');
    response_json(['error' => 'Отправка заявок пока не настроена. Позвоните нам по телефону'], 503);
}
require_once __DIR__ . '/mailer.php';
try {
    send_site_message($state['smtp'], $recipient, $subject, $body);
} catch (Throwable $e) {
    error_log('Landing email: ' . get_class($e) . ' (' . substr($e->getMessage(), 0, 30) . ')');
    response_json(['error' => 'Заявку не удалось отправить. Позвоните нам по телефону'], 502);
}
response_json(['ok' => true, 'message' => 'Заявка принята почтовым сервером. Мы свяжемся с вами']);
