<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send using authenticated SMTP. The sender must match SMTP username.
 * The password and contacts remain in state.php outside the document root.
 */
function send_site_message(array $smtp, string $recipient, string $subject, string $body): void {
    $host = $smtp['host'] ?? '';
    $user = $smtp['user'] ?? '';
    $password = $smtp['password'] ?? '';
    if (!in_array($host, ['smtp.timeweb.ru', 'smtp.yandex.ru'], true) ||
        !is_string($user) || !filter_var($user, FILTER_VALIDATE_EMAIL) ||
        !is_string($password) || $password === '') {
        throw new RuntimeException('SMTP_NOT_CONFIGURED');
    }
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('SMTP vendor/autoload.php missing');
        throw new RuntimeException('SMTP_DEPENDENCY_UNAVAILABLE');
    }
    require_once $autoload;

    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->Port = 587;
    $mailer->SMTPAuth = true;
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->SMTPAutoTLS = true;
    $mailer->Timeout = 12;
    $mailer->CharSet = PHPMailer::CHARSET_UTF8;
    // Yandex's personal mail accounts use the local part for SMTP authorization.
    // The message From address must still be the full verified mailbox.
    $mailer->Username = $host === 'smtp.yandex.ru' && str_ends_with($user, '@yandex.ru')
        ? substr($user, 0, strpos($user, '@'))
        : $user;
    $mailer->Password = $password;
    $mailer->setFrom($user, 'Путь без преград');
    $mailer->addAddress($recipient);
    $mailer->Subject = $subject;
    $mailer->Body = $body;
    $mailer->isHTML(false);

    try {
        $mailer->send();
    } catch (Exception $e) {
        $reason = $mailer->ErrorInfo;
        if ($password !== '') $reason = str_replace($password, '[secret]', $reason);
        $reason = substr(str_replace(["\r", "\n"], ' ', $reason), 0, 300);
        error_log('PBP SMTP delivery attempt failed: ' . $reason);
        // Only an authenticated admin test can show a limited diagnostic.
        throw new RuntimeException('SMTP_SEND_FAILED: ' . $reason, 0, $e);
    }
}
