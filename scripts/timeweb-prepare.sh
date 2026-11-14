#!/usr/bin/env bash
# Run over authenticated SSH with SITE_ROOT_B64 set by the deployment workflow.
set -euo pipefail
umask 077
root="$(printf '%s' "$SITE_ROOT_B64" | base64 -d)"
if [[ ! "$root" =~ ^/[a-zA-Z0-9._/-]+$ || "$root" == "/" || "$root" == *"/../"* ]]; then
  echo 'Invalid site root' >&2; exit 1
fi
if [[ ! -d "$root/public_html" && ! -L "$root/public_html" ]]; then
  echo 'Existing public_html directory is missing; refusing migration' >&2; exit 1
fi
if [[ -L "$root/web" || -L "$root/private" || -L "$root/private/admin-api" ]]; then
  echo 'Refusing symlinked deployment/storage directories' >&2; exit 1
fi
mkdir -p "$root/web" "$root/private/admin-api"
# Do not overwrite state created after earlier deployment attempts.
if [[ -d "$root/.pbp-private" ]]; then
  for file in state.php form-challenge.key; do
    if [[ -f "$root/.pbp-private/$file" && ! -e "$root/private/$file" ]]; then
      cp -p "$root/.pbp-private/$file" "$root/private/$file"
    fi
  done
fi
echo 'Website directories prepared without changing the live document root.'
