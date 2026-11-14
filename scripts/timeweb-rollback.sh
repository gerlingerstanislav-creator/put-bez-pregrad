#!/usr/bin/env bash
# Restores the previous site only if this workflow performed the first cutover.
set -euo pipefail
root="$(printf '%s' "$SITE_ROOT_B64" | base64 -d)"
if [[ ! "$root" =~ ^/[a-zA-Z0-9._/-]+$ || "$root" == "/" || "$root" == *"/../"* ]]; then exit 1; fi
marker="$root/.pbp-cutover-pending"
[[ -f "$marker" ]] || exit 0
read -r backup < "$marker"
[[ "$backup" == "$root"/public_html.pbp-backup-* &&
   -d "$backup" && -L "$root/public_html" ]] || { echo 'Unsafe rollback state' >&2; exit 1; }
[[ "$(readlink -f "$root/public_html")" == "$(realpath "$root/web")" ]] ||
  { echo 'Document root changed independently; refusing rollback' >&2; exit 1; }
rm "$root/public_html"
mv "$backup" "$root/public_html"
rm "$marker"
echo 'Original document root restored.'
