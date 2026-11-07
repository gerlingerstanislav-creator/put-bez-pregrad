<?php
declare(strict_types=1);

define('SITE_STORAGE', dirname(__DIR__, 2) . '/.pbp-private');
const INITIAL_PASSWORD_HASH = '$2y$12$aUqing2M5ALbON0zX/cM6uA.UciO4PSODn.f1O2az9iB058UR2i8q';

function defaults(): array {
    return [
        'primary_phone' => '+7 (8422) 71-73-03',
        'secondary_phone' => '+7 (967) 771-73-03',
        'contact_email' => 'bezpregrad73@yandex.ru',
        'request_email' => 'bezpregrad73@yandex.ru',
        'max_url' => 'https://max.ru/u/f9LHodD0cOIMqlrwFHrhALk5VeAh-knTiBJCLnhZSFM3iMksBuOEXJ6JMec',
    ];
}
function response_json(array $body, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function only_method(string $allowed): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $allowed) {
        header('Allow: ' . $allowed);
        response_json(['error' => 'Метод не поддерживается'], 405);
    }
}
function same_origin(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($origin !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        $requestHost = strtolower(explode(':', $host)[0]);
        if (!is_string($originHost) || strtolower($originHost) !== $requestHost) {
            response_json(['error' => 'Недопустимый источник запроса'], 403);
        }
    }
}
function input_json(): array {
    $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($type !== 'application/json') {
        response_json(['error' => 'Ожидается JSON'], 415);
    }
    $raw = file_get_contents('php://input', false, null, 0, 12001);
    if ($raw === false || strlen($raw) > 12000) response_json(['error' => 'Слишком большие данные'], 413);
    try {
        $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        response_json(['error' => 'Неверный формат данных'], 400);
    }
    if (!is_array($data) || !str_starts_with(ltrim($raw), '{')) response_json(['error' => 'Неверный формат'], 400);
    return $data;
}
function storage_ready(): void {
    if (!is_dir(SITE_STORAGE) && !mkdir(SITE_STORAGE, 0700, true) && !is_dir(SITE_STORAGE)) {
        response_json(['error' => 'Хранилище недоступно'], 503);
    }
}
function state_file(): string { return SITE_STORAGE . '/state.php'; }
function read_state_file(): array {
    $file = state_file();
    if (!is_file($file)) return ['password_hash' => INITIAL_PASSWORD_HASH, 'settings' => defaults()];
    $raw = file_get_contents($file);
    $prefix = "<?php http_response_code(404); exit; ?>\n";
    if (!is_string($raw) || !str_starts_with($raw, $prefix)) {
        response_json(['error' => 'Ошибка чтения настроек'], 503);
    }
    try { $data = json_decode(substr($raw, strlen($prefix)), true, 16, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { response_json(['error' => 'Ошибка чтения настроек'], 503); }
    if (!is_array($data) || !is_array($data['settings'] ?? null) || !is_string($data['password_hash'] ?? null)) {
        response_json(['error' => 'Ошибка чтения настроек'], 503);
    }
    return $data;
}
function current_state(): array {
    storage_ready();
    return read_state_file();
}
function update_state(callable $update): array {
    storage_ready();
    $lock = fopen(SITE_STORAGE . '/state.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) response_json(['error' => 'Невозможно сохранить настройки'], 503);
    try {
        $state = read_state_file();
        $next = $update($state);
        if (!is_array($next)) throw new RuntimeException('Invalid state');
        $temp = SITE_STORAGE . '/.state-' . bin2hex(random_bytes(10)) . '.tmp';
        $contents = "<?php http_response_code(404); exit; ?>\n" .
            json_encode($next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($temp, $contents, LOCK_EX) === false) throw new RuntimeException('Cannot write state');
        chmod($temp, 0600);
        if (!rename($temp, state_file())) throw new RuntimeException('Cannot replace state');
        return $next;
    } catch (Throwable $e) {
        error_log('Site settings write failed: ' . get_class($e));
        response_json(['error' => 'Невозможно сохранить настройки'], 503);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
function throttle(string $action, int $limit, int $seconds): bool {
    storage_ready();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $path = SITE_STORAGE . '/rate_' . hash('sha256', $action . ':' . $ip) . '.php';
    $handle = fopen($path, 'c+');
    if ($handle === false) return false;
    try {
        if (!flock($handle, LOCK_EX)) return false;
        $raw = stream_get_contents($handle);
        $parts = is_string($raw) ? explode(',', trim($raw)) : [];
        $window = (int) ($parts[0] ?? 0);
        $count = (int) ($parts[1] ?? 0);
        $now = time();
        if ($now - $window >= $seconds || $window > $now) { $window = $now; $count = 0; }
        $count++;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $window . ',' . $count);
        fflush($handle);
        return $count <= $limit;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
function start_admin_session(): void {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('pbp_admin');
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['lifetime' => 0, 'path' => '/api/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function validate_csrf(array $data): void {
    if (!is_string($data['csrf'] ?? null) ||
        !hash_equals((string) ($_SESSION['csrf'] ?? ''), $data['csrf'])) {
        response_json(['error' => 'Недействительный токен сессии'], 403);
    }
}
function require_admin(): void {
    if (($_SESSION['admin'] ?? false) !== true) response_json(['error' => 'Требуется вход'], 401);
}
function clean_single_line(mixed $value, int $limit): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    if ($value === '' || strlen($value) > $limit || preg_match('/[\x00-\x1F\x7F]/u', $value)) return null;
    return $value;
}


/** Normalize a full Russian phone; support 8/+7/10-digit national input. */
function format_ru_phone(mixed $value): ?string {
    if (!is_string($value) || strlen($value) > 80) return null;
    $digits = preg_replace('/\D+/', '', $value);
    if (!is_string($digits)) return null;
    if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) !== 10) return null;
    if (str_starts_with($digits, '8422')) {
        return '+7 (8422) ' . substr($digits, 4, 2) . '-' . substr($digits, 6, 2) . '-' . substr($digits, 8, 2);
    }
    return '+7 (' . substr($digits, 0, 3) . ') ' .
        substr($digits, 3, 3) . '-' . substr($digits, 6, 2) . '-' . substr($digits, 8, 2);
}
/** Load the IDN polyfill when the host has no intl extension. */
function ensure_idn_support(): bool {
    if (function_exists('idn_to_ascii') && function_exists('idn_to_utf8')) return true;
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) return false;
    require_once $autoload;
    return function_exists('idn_to_ascii') && function_exists('idn_to_utf8');
}

/** Canonical SMTP address: ASCII local part and punycoded international domain. */
function normalized_email(mixed $value): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    if ($value === '' || strlen($value) > 320 ||
        preg_match('/[\x00-\x20\x7F]/', $value)) return null;
    $at = strrpos($value, '@');
    if ($at === false) return null;
    $local = substr($value, 0, $at);
    $domain = substr($value, $at + 1);
    if ($local === '' || $domain === '' ||
        !preg_match('/^[\x21-\x7E]+$/', $local)) return null;
    if (preg_match('/[^\x00-\x7F]/', $domain)) {
        if (!ensure_idn_support()) return null;
        $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (!is_string($ascii) || $ascii === '') return null;
        $domain = $ascii;
    }
    $email = $local . '@' . strtolower($domain);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
}

function email_for_display(mixed $value): string {
    $ascii = normalized_email($value);
    if ($ascii === null) return is_string($value) ? $value : '';
    $at = strrpos($ascii, '@');
    $domain = substr($ascii, $at + 1);
    if (!str_contains($domain, 'xn--') || !ensure_idn_support()) return $ascii;
    $unicode = idn_to_utf8($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
    return is_string($unicode) && $unicode !== ''
        ? substr($ascii, 0, $at + 1) . $unicode
        : $ascii;
}
function smtp_public_status(array $state): array {
    $smtp = $state['smtp'] ?? [];
    return [
        'host' => in_array($smtp['host'] ?? '', ['smtp.timeweb.ru', 'smtp.yandex.ru'], true) ? $smtp['host'] : 'smtp.timeweb.ru',
        'user' => email_for_display($smtp['user'] ?? ''),
        'configured' => is_string($smtp['password'] ?? null) && $smtp['password'] !== '',
    ];
}
