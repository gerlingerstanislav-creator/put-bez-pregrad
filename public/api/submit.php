<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
only_method('POST');
same_origin();
$data = input_json();
if (!empty($data['website'])) response_json(['ok' => true]);
if (!throttle('public-request', 5, 600)) response_json(['error' => 'Слишком много заявок. Позвоните нам по телефону'], 429);
$from = clean_single_line($data['departure'] ?? null, 800);
$to = clean_single_line($data['destination'] ?? null, 800);
$phone = clean_single_line($data['contact'] ?? null, 100);
$name = $data['passenger'] ?? '';
$name = $name === '' ? 'Не указано' : clean_single_line($name, 320);
if (!$from || !$to || !$phone || !$name || empty($data['consent'])) {
    response_json(['error' => 'Заполните обязательные поля и согласие'], 422);
}
if (!preg_match('/^[+\d()\s.-]{6,100}$/', $phone)) {
    response_json(['error' => 'Проверьте номер телефона'], 422);
}
$recipient = current_state()['settings']['request_email'] ?? '';
if (!is_string($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    response_json(['error' => 'Отправка временно недоступна. Позвоните нам'], 503);
}
$subject = '=?UTF-8?B?' . base64_encode('Новая заявка — Путь без преград') . '?=';
$body = "Новая заявка на перевозку\n\n" .
    "Откуда: {$from}\nКуда: {$to}\nИмя: {$name}\nТелефон: {$phone}\n";
$headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
$ok = @mail($recipient, $subject, $body, $headers);
if (!$ok) {
    error_log('Landing email: local mail transport rejected message');
    response_json(['error' => 'Заявку не удалось отправить. Позвоните нам по телефону'], 502);
}
response_json(['ok' => true, 'message' => 'Заявка принята к отправке. Мы свяжемся с вами']);
