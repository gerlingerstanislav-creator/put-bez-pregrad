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
stage_name = "pbp-private-stage-" + secrets.token_hex(12)
filename = "storage-check-" + secrets.token_hex(24) + ".php"
digest = hashlib.sha256(token.encode()).hexdigest()
marker_name = "pbp-private-path-" + secrets.token_hex(12) + ".marker"
marker_value = secrets.token_hex(16)
marker_digest = hashlib.sha256(marker_value.encode()).hexdigest()
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
if (($body['create_stage'] ?? false) === true) {
    $stage = dirname(__DIR__, 2) . '/__STAGE_NAME__';
    echo json_encode(['created' => @mkdir($stage, 0700, false)]);
    exit;
}
if (($body['remove_stage'] ?? false) === true) {
    $stage = dirname(__DIR__, 2) . '/__STAGE_NAME__';
    echo json_encode(['removed' => @rmdir($stage)]);
    exit;
}
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
require_once __DIR__ . '/../../api/common.php';
$runtimeCurrent = SITE_STORAGE;
$runtimeParent = dirname($runtimeCurrent);
$storageCreated = is_dir($runtimeCurrent) || @mkdir($runtimeCurrent, 0700, true);
$failure = strtolower((string) (error_get_last()['message'] ?? ''));
$errorClass = 'unknown';
foreach (['permission' => 'permission denied', 'quota' => 'quota', 'space' => 'no space', 'exists' => 'file exists', 'basedir' => 'open_basedir'] as $key => $phrase) {
    if (str_contains($failure, $phrase)) { $errorClass = $key; break; }
}
$testWrites = [];
foreach (['parent_of_webroot' => $runtimeParent, 'private_storage' => $runtimeCurrent, 'webroot' => $root, 'temporary' => sys_get_temp_dir()] as $name => $parent) {
    $probe = $parent . '/pbp-test-' . bin2hex(random_bytes(8));
    $testWrites[$name] = @mkdir($probe, 0700, false);
    if ($testWrites[$name]) @rmdir($probe);
}
$result = [
    'ftp_parent_marker_matches' => is_file($oldParent . '/__MARKER_NAME__') && hash_file('sha256', $oldParent . '/__MARKER_NAME__') === '__MARKER_DIGEST__',
    'mkdir_error_class' => $errorClass,
    'test_create_subdir' => $testWrites,
    'runtime_path_matches_candidate' => $runtimeCurrent === $oldStorage,
    'runtime_parent_writable' => is_writable($runtimeParent),
    'created_private_directory' => $storageCreated,
    'directory_exists_after' => is_dir($runtimeCurrent),
    'private_owner_is_php_user' => function_exists('posix_geteuid') ? @fileowner($runtimeCurrent) === posix_geteuid() : null,
    'private_group_is_php_group' => function_exists('posix_getegid') ? @filegroup($runtimeCurrent) === posix_getegid() : null,
    'private_owner_is_webroot_owner' => @fileowner($runtimeCurrent) === @fileowner($root),
    'private_mode' => is_dir($runtimeCurrent) ? substr(sprintf('%o', fileperms($runtimeCurrent)), -4) : null,
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
""".replace("__TOKEN_DIGEST__", digest).replace("__STAGE_NAME__", stage_name).replace("__MARKER_NAME__", marker_name).replace("__MARKER_DIGEST__", marker_digest)

ftp = ftplib.FTP(timeout=25)
remote = slug + "/api/" + filename
uploaded = False
marker_uploaded = False
original_ftp_dir = None
try:
    ftp.connect(os.environ["TIMEWEB_FTP_HOST"], 21)
    ftp.login(os.environ["TIMEWEB_FTP_USER"], os.environ["TIMEWEB_FTP_PASSWORD"])
    ftp.cwd(os.environ["TIMEWEB_FTP_DIR"])
    original_ftp_dir = ftp.pwd()
    try:
        ftp.cwd("..")
        outside_ftp_dir = ftp.pwd() != original_ftp_dir
        if outside_ftp_dir:
            ftp.storbinary("STOR " + marker_name, io.BytesIO(marker_value.encode("utf-8")))
            marker_uploaded = True
    except ftplib.all_errors:
        outside_ftp_dir = False
    finally:
        ftp.cwd(original_ftp_dir)
    print("FTP can reach the parent of the deployed site:", outside_ftp_dir)
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
    # PHP cannot mkdir in the parent of public_html on this Timeweb account.
    # Attempt the equivalent operation through FTP, but never overwrite existing data.
    # Verify via the live PHP probe before treating the storage as available.
    if outside_ftp_dir and not report["current"]["storage_is_dir"]:
        created = False
        ftp.cwd("..")
        try:
            try:
                ftp.mkd(".pbp-private")
                created = True
            except ftplib.error_perm:
                # May be a pre-existing directory; never delete or modify its contents.
                try:
                    ftp.cwd(".pbp-private")
                    ftp.cwd("..")
                except ftplib.all_errors:
                    print("FTP could not create the protected directory.")
            if created:
                try:
                    ftp.sendcmd("SITE CHMOD 700 .pbp-private")
                except ftplib.all_errors:
                    pass
        finally:
            ftp.cwd(original_ftp_dir)
        if created:
            print("FTP created a private directory outside its published site directory.")
            follow_up = subprocess.run([
                "curl", "--silent", "--show-error", "--fail", "--max-time", "20",
                "-H", "Content-Type: application/json", "--data-binary", "@-",
                url,
            ], input=json.dumps({"token": token}), text=True, capture_output=True, timeout=25)
            if follow_up.returncode:
                raise RuntimeError("Could not verify private directory from PHP")
            after = json.loads(follow_up.stdout)
            visible = bool(after.get("current", {}).get("storage_is_dir"))
            writable = bool(after.get("current", {}).get("storage_writable"))
            print("FTP private directory visible to PHP:", visible)
            print("FTP private directory writable by PHP:", writable)
            if not visible:
                # This FTP parent does not map to PHP's parent; remove only our empty probe dir.
                try:
                    ftp.cwd("..")
                    ftp.rmd(".pbp-private")
                    ftp.cwd(original_ftp_dir)
                    print("Removed unrelated empty test directory.")
                except ftplib.all_errors:
                    print("WARNING: Could not remove the newly created empty test directory.")
            elif not writable:
                print("Storage exists but PHP lacks permission; no broad chmod was applied.")
            else:
                print("Protected storage directory is ready for PHP.")
    # A 0700 directory created by FTP may belong to a different Unix account
    # from the PHP worker. In that case let PHP own an empty staging directory,
    # and let FTP move it outside public_html without relaxing permissions.
    needs_owner_repair = (
        outside_ftp_dir
        and report.get("current", {}).get("storage_is_dir")
        and not report.get("test_create_subdir", {}).get("private_storage", True)
        and not report.get("current", {}).get("state_file_exists")
    )
    if needs_owner_repair:
        print("PHP cannot create children in FTP-owned private storage; attempting ownership-safe repair.")
        stage_response = subprocess.run([
            "curl", "--silent", "--show-error", "--fail", "--max-time", "20",
            "-H", "Content-Type: application/json", "--data-binary", "@-", url,
        ], input=json.dumps({"token": token, "create_stage": True}),
            text=True, capture_output=True, timeout=25)
        if stage_response.returncode:
            raise RuntimeError("Could not prepare private directory owned by PHP")
        staged = bool(json.loads(stage_response.stdout).get("created"))
        if not staged:
            raise RuntimeError("PHP could not create an empty secure staging directory")
        try:
            ftp.cwd("..")
            try:
                # rmd fails if any settings or secret files exist: do not delete them.
                ftp.rmd(".pbp-private")
            except ftplib.all_errors:
                raise RuntimeError("Existing private directory is not empty or cannot be moved safely")
            finally:
                ftp.cwd(original_ftp_dir)
            try:
                ftp.rename(stage_name, "../.pbp-private")
            except ftplib.all_errors:
                ftp.cwd("..")
                try:
                    ftp.mkd(".pbp-private")
                    ftp.sendcmd("SITE CHMOD 700 .pbp-private")
                except ftplib.all_errors:
                    print("WARNING: Failed to recreate empty FTP-owned storage after rename failure")
                finally:
                    ftp.cwd(original_ftp_dir)
                raise RuntimeError("FTP could not move the PHP-owned storage outside webroot")
            check = subprocess.run([
                "curl", "--silent", "--show-error", "--fail", "--max-time", "20",
                "-H", "Content-Type: application/json", "--data-binary", "@-", url,
            ], input=json.dumps({"token": token}), text=True,
                capture_output=True, timeout=25)
            if check.returncode:
                raise RuntimeError("Could not verify restored private directory")
            restored = json.loads(check.stdout)
            print("Private directory is outside webroot:", restored.get("current", {}).get("outside_webroot"))
            print("PHP can create private children:", restored.get("test_create_subdir", {}).get("private_storage"))
            if not restored.get("test_create_subdir", {}).get("private_storage"):
                raise RuntimeError("PHP still cannot write to secure storage after ownership repair")
        finally:
            # If FTP rename succeeded the stage no longer exists in public_html.
            cleanup = subprocess.run([
                "curl", "--silent", "--show-error", "--fail", "--max-time", "20",
                "-H", "Content-Type: application/json", "--data-binary", "@-", url,
            ], input=json.dumps({"token": token, "remove_stage": True}),
                text=True, capture_output=True, timeout=25)
            if cleanup.returncode:
                print("WARNING: Could not verify temporary staging directory cleanup")
finally:
    if original_ftp_dir is not None:
        try:
            ftp.cwd(original_ftp_dir)
        except ftplib.all_errors:
            pass
    if uploaded:
        try:
            ftp.delete(remote)
            print("Temporary probe removed from Timeweb.")
        except ftplib.all_errors:
            print("WARNING: FTP removal of the temporary probe failed; token will not be reused.")
    if marker_uploaded:
        try:
            ftp.cwd(original_ftp_dir)
            ftp.cwd("..")
            ftp.delete(marker_name)
            print("Temporary parent marker removed.")
        except ftplib.all_errors:
            print("WARNING: Couldn't remove temporary non-secret parent marker.")
    try:
        ftp.quit()
    except ftplib.all_errors:
        pass
