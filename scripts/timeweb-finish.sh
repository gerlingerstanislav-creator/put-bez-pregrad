#!/usr/bin/env bash
set -euo pipefail
root="$(printf '%s' "$SITE_ROOT_B64" | base64 -d)"
if [[ ! "$root" =~ ^/[a-zA-Z0-9._/-]+$ || "$root" == "/" || "$root" == *"/../"* ]]; then exit 1; fi
[[ -L "$root/public_html" ]] || exit 1
[[ "$(readlink -f "$root/public_html")" == "$(realpath "$root/web")" ]] || exit 1
rm -f "$root/.pbp-cutover-pending"
echo 'Cutover confirmed. Old public_html backup retained.'
