#!/usr/bin/env bash
# SSH-side cutover: checks HTTP PHP write access before altering public_html.
set -euo pipefail
umask 077
root="$(printf '%s' "$SITE_ROOT_B64" | base64 -d)"
if [[ ! "$root" =~ ^/[a-zA-Z0-9._/-]+$ || "$root" == "/" || "$root" == *"/../"* ]]; then
  echo 'Invalid site root' >&2; exit 1
fi
[[ -s "$root/web/index.html" && -s "$root/web/api/public.php" &&
   -s "$root/private/admin-api/admin.php" ]] || { echo 'Staged site incomplete' >&2; exit 1; }
if [[ -L "$root/public_html" ]]; then
  [[ "$(readlink -f "$root/public_html")" == "$(realpath "$root/web")" ]] ||
    { echo 'public_html points to an unexpected location' >&2; exit 1; }
  echo 'Document root already targets web/.'
  exit 0
fi
[[ -d "$root/public_html" && ! -e "$root/.pbp-cutover-pending" ]] ||
  { echo 'Document root or previous cutover state is unexpected' >&2; exit 1; }

nonce="$(od -An -N24 -tx1 /dev/urandom | tr -d ' \n')"
probe_name="pbp-storage-check-$nonce.php"
probe="$root/public_html/$probe_name"
cat > "$probe" <<PHP
<?php
declare(strict_types=1);
header('Content-Type: text/plain');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !hash_equals('$nonce', (string) (\$_POST['token'] ?? ''))) {
    http_response_code(404); exit;
}
\$dir = dirname(__DIR__) . '/private';
\$path = \$dir . '/.write-check-$nonce';
\$ok = is_dir(\$dir) && !is_link(\$dir)
    && @file_put_contents(\$path, random_bytes(32), LOCK_EX) === 32
    && @file_get_contents(\$path) !== false;
@unlink(\$path);
if (!\$ok) { http_response_code(503); echo 'unavailable'; exit; }
echo 'ready';
PHP
trap 'rm -f "$probe"' EXIT
if [[ "$(curl --silent --show-error --max-time 15 --request POST --data-urlencode "token=$nonce" "https://bpreg.tw1.ru/$probe_name" || true)" != "ready" ]]; then
  echo 'PHP cannot write private storage. Existing website was NOT switched.' >&2
  exit 1
fi
rm -f "$probe"
trap - EXIT

backup="$root/public_html.pbp-backup-$(date -u +%Y%m%d%H%M%S)"
[[ ! -e "$backup" ]] || { echo 'Backup filename already exists' >&2; exit 1; }
mv "$root/public_html" "$backup"
if ! ln -s web "$root/public_html"; then
  mv "$backup" "$root/public_html"
  echo 'Could not activate new document root; original restored' >&2
  exit 1
fi
# Fast health gate; on failure restore the previous live root immediately.
if ! curl -fsS --max-time 20 "https://bpreg.tw1.ru/api/public.php" | grep -q '"primary_phone"'; then
  rm "$root/public_html"
  mv "$backup" "$root/public_html"
  echo 'New PHP API failed; original document root restored.' >&2
  exit 1
fi
printf '%s\n' "$backup" > "$root/.pbp-cutover-pending"
echo 'New webroot activated; previous public_html preserved for rollback.'
