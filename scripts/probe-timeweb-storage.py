"""One-time, read-only Timeweb filesystem probe. No path or credential is printed."""
import ftplib
import hashlib
import io
import json
import os
import secrets
import subprocess

slug = os.environ["PBP_ADMIN_PATH"]
if not slug or not slug.replace("-", "").isalnum():
    raise SystemExit("Invalid admin path secret")
token = secrets.token_hex(32)
filename = "storage-check-" + secrets.token_hex(24) + ".php"
digest = hashlib.sha256(token.encode()).hexdigest()
print("::add-mask::" + slug)
print("::add-mask::" + filename)

php = r"""<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(404); exit; }
$body = json_decode((string) file_get_contents('php://input'), true);
$provided = is_array($body) && is_string($body['token'] ?? null) ? $body['token'] : '';
if (!hash_equals('__TOKEN_DIGEST__', hash('sha256', $provided))) { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$oldParent = dirname(__DIR__, 3);
$oldStorage = $oldParent . '/.pbp-private';
$home = getenv('HOME');
function candidate(string $parent, string $base): array {
    $storage = $parent . '/.pbp-private';
    return [
        'parent_exists' => is_dir($parent),
        'parent_writable' => is_writable($parent),
        'storage_is_dir' => is_dir($storage),
        'storage_is_file' => is_file($storage),
        'storage_writable' => is_writable($storage),
        'state_file_exists' => is_file($storage . '/state.php'),
        'outside_webroot' => !str_starts_with(rtrim($parent, '/') . '/', rtrim($base, '/') . '/')
    ];
}
$result = [
    'current' => candidate($oldParent, $root),
    'ancestor_up_one' => candidate(dirname($oldParent), $root),
    'ancestor_up_two' => candidate(dirname($oldParent, 2), $root),
    'home' => $home ? candidate($home, $root) : null,
    'temp' => candidate(sys_get_temp_dir(), $root),
    'webroot' => candidate($root, $root),
    'open_basedir_enabled' => (bool) ini_get('open_basedir'),
    'php_major_minor' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION
];
echo json_encode($result, JSON_UNESCAPED_SLASHES);
""".replace("__TOKEN_DIGEST__", digest)

ftp = ftplib.FTP(timeout=25)
remote = slug + "/api/" + filename
uploaded = False
try:
    ftp.connect(os.environ["TIMEWEB_FTP_HOST"], 21)
    ftp.login(os.environ["TIMEWEB_FTP_USER"], os.environ["TIMEWEB_FTP_PASSWORD"])
    ftp.cwd(os.environ["TIMEWEB_FTP_DIR"])
    ftp.storbinary("STOR " + remote, io.BytesIO(php.encode("utf-8")))
    uploaded = True
    url = "https://bpreg.tw1.ru/" + remote
    response = subprocess.run([
        "curl", "--silent", "--show-error", "--fail", "--max-time", "20",
        "-H", "Content-Type: application/json",
        "--data-binary", "@-", url
    ], input=json.dumps({"token": token}), text=True, capture_output=True, timeout=25)
    if response.returncode != 0:
        raise RuntimeError("Private filesystem probe could not be queried over HTTPS")
    report = json.loads(response.stdout)
    if not isinstance(report, dict) or "current" not in report:
        raise RuntimeError("Filesystem probe returned unexpected response")
    print("Timeweb filesystem capability report:")
    print(json.dumps(report, ensure_ascii=False, sort_keys=True))
finally:
    if uploaded:
        try:
            ftp.delete(remote)
            print("Temporary probe removed from Timeweb.")
        except ftplib.all_errors:
            print("WARNING: FTP removal of the temporary probe failed; token will not be reused.")
    try:
        ftp.quit()
    except ftplib.all_errors:
        pass
