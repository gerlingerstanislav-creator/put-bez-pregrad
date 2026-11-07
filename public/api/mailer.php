<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/** Send with authenticated encrypted SMTP. Never retain request contents on disk. */
function send_site_message(array $smtp, string $recipient, string $subject, string $body): void {
    $host = $smtp['host'] ?? '';
    // Migrate Unicode-domain SMTP usernames transparently without rewriting saved credentials.
    $user = normalized_email($smtp['user'] ?? null);
    $to = normalized_email($recipient);
    $password = $smtp['password'] ?? '';
    if (!in_array($host, ['smtp.timeweb.ru', 'smtp.yandex.ru'], true) ||
        $user === null || $to === null || !is_string($password) || $password === '') {
        throw new RuntimeException('SMTP_NOT_CONFIGURED');
    }
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('SMTP_DEPENDENCY_UNAVAILABLE');
    require_once $autoload;

    $mailer = new PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->Host = $host;
        // Timeweb supports both 587/STARTTLS and 465/implicit TLS; try 465
        // because the current server reports a connection failure on port 587.
        // Keep Yandex on its existing supported 587/STARTTLS configuration.
        $mailer->Port = $host === 'smtp.timeweb.ru' ? 465 : 587;
        $mailer->SMTPAuth = true;
        $mailer->SMTPSecure = $host === 'smtp.timeweb.ru'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->SMTPAutoTLS = true;
        $mailer->Timeout = 12;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->Username = $host === 'smtp.yandex.ru' && str_ends_with($user, '@yandex.ru')
            ? substr($user, 0, strpos($user, '@'))
            : $user;
        $mailer->Password = $password;
        // Timeweb requires From to match the full authenticated mailbox.
        $mailer->setFrom($user, 'Путь без преград');
        $mailer->addAddress($to);
        $mailer->Subject = $subject;
        $mailer->Body = $body;
        $mailer->isHTML(false);
        $mailer->send();
    } catch (Exception $e) {
        // Do not expose SMTP transcript, address or password to public clients.
        $reason = $mailer->ErrorInfo ?: $e->getMessage();
        $code = classify_smtp_error($reason);
        error_log('PBP SMTP failure: ' . $code);
        throw new RuntimeException($code, 0, $e);
    }
}

function classify_smtp_error(string $message): string {
    $s = strtolower($message);
    if (str_contains($s, 'invalid address') || str_contains($s, 'invalid header')) return 'SMTP_ADDRESS_INVALID';
    if (str_contains($s, 'authenticate') || str_contains($s, 'authentication') ||
        str_contains($s, 'username') || str_contains($s, 'password')) return 'SMTP_AUTH_FAILED';
    if (str_contains($s, 'certificate') || str_contains($s, 'starttls') ||
        str_contains($s, 'tls') || str_contains($s, 'ssl')) return 'SMTP_TLS_FAILED';
    if (str_contains($s, 'connect') || str_contains($s, 'timed out') ||
        str_contains($s, 'network') || str_contains($s, 'refused')) return 'SMTP_CONNECTION_FAILED';
    if (str_contains($s, 'sender') || str_contains($s, 'recipient') ||
        str_contains($s, 'not accepted')) return 'SMTP_ADDRESS_REJECTED';
    return 'SMTP_SEND_FAILED';
}
