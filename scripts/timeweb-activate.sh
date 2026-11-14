#!/usr/bin/env bash
# One-time activation for the main Timeweb site. Run as the hosting account owner over SSH.
set -euo pipefail
umask 077
site="$HOME/put-bez-pregrad"
docroot="$HOME/public_html"
if [[ -L "$docroot" ]]; then
  if [[ "$(readlink -f "$docroot")" == "$(realpath "$site/public")" ]]; then
    echo 'Already activated'; exit 0
  fi
  echo 'public_html is an unrelated symlink: stop' >&2; exit 1
fi
[[ -d "$docroot" && -d "$site/public" && -d "$site/app" ]] ||
  { echo 'Missing existing website or staged public/app' >&2; exit 1; }
[[ ! -L "$site" && ! -L "$site/public" && ! -L "$site/app" && ! -L "$site/storage" ]] ||
  { echo 'Refusing unexpected symlink' >&2; exit 1; }
[[ -s "$site/public/index.html" && -s "$site/public/api/public.php" &&
   -s "$site/app/api/common.php" && -s "$site/app/admin-api/admin.php" ]] ||
  { echo 'Incomplete staged release' >&2; exit 1; }
mkdir -p "$site/storage"
chmod 700 "$site/storage"

# Do not destroy preexisting credentials or contacts.
if [[ -f "$HOME/.pbp-private/state.php" && ! -e "$site/storage/state.php" ]]; then
  cp -p "$HOME/.pbp-private/state.php" "$site/storage/state.php"
  chmod 600 "$site/storage/state.php"
fi
if [[ -f "$HOME/.pbp-private/form-challenge.key" &&
      ! -e "$site/storage/form-challenge.key" ]]; then
  cp -p "$HOME/.pbp-private/form-challenge.key" "$site/storage/form-challenge.key"
  chmod 600 "$site/storage/form-challenge.key"
fi

# PHP must prove write access as the LIVE web process, not only as the SSH user.
token="$(od -An -N24 -tx1 /dev/urandom | tr -d ' \n')"
probe_filename="pbp-check-$token.php"
probe="$docroot/$probe_filename"
cat > "$probe" <<PHP
<?php
declare(strict_types=1);
if ((\$_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
    !hash_equals('$token', (string) (\$_POST['token'] ?? ''))) {
    http_response_code(404); exit;
}
header('Content-Type: text/plain');
header('Cache-Control: no-store');
\$directory = dirname(__DIR__) . '/put-bez-pregrad/storage';
\$file = \$directory . '/.check-$token';
\$bytes = random_bytes(32);
\$ok = @file_put_contents(\$file, \$bytes, LOCK_EX) === 32
  && @file_get_contents(\$file) === \$bytes;
@unlink(\$file);
if (!\$ok) { http_response_code(503); echo 'write-denied'; exit; }
echo 'ready';
PHP
chmod 0644 "$probe"
trap 'rm -f "$probe"' EXIT
response="$(curl -fsS --max-time 20 -X POST --data-urlencode "token=$token" \
  "https://bpreg.tw1.ru/$probe_filename" 2>/dev/null || true)"
if [[ "$response" != "ready" ]]; then
  echo 'PHP cannot write storage. The live site has NOT been switched.' >&2
  exit 1
fi
rm -f "$probe"
trap - EXIT

backup="$HOME/public_html.pbp-backup-$(date -u +%Y%m%d%H%M%S)"
[[ ! -e "$backup" ]] || { echo 'Backup already exists' >&2; exit 1; }
mv "$docroot" "$backup"
if ! ln -s "$site/public" "$docroot"; then
  mv "$backup" "$docroot"
  echo 'Could not create public_html symlink, rolled back' >&2; exit 1
fi
if ! curl -fsS --max-time 20 https://bpreg.tw1.ru/api/public.php | grep -q '"primary_phone"'; then
  rm "$docroot"
  mv "$backup" "$docroot"
  echo 'New API not healthy; old website restored' >&2
  exit 1
fi
printf '%s\n' "$backup" > "$site/.previous-public-html"
printf 'pbp-isolated-live-v1\n' > "$site/.pbp-live-root"
echo 'New site activated. Old public_html is retained for rollback.'
