#!/usr/bin/env bash
# Fix ownership + permissions on the uploads/storage tree after a deploy.
#
# Why this exists: on a VPS, "git pull" never changes file ownership. If the
# project was first set up as one user (e.g. your SSH login) and the web
# server runs as another (www-data, apache, nginx), every deploy after the
# first can silently leave public/uploads owned by the wrong user again —
# this is the #1 cause of "photo/logo upload fails on the VPS but works
# locally". Run this once after cloning and again after every deploy that
# re-extracts or re-clones the project (plain "git pull" in place does not
# need it re-run, but it is always safe to re-run).
#
# Usage (from the project root, as root or with sudo):
#   sudo bash scripts/fix-permissions.sh
#   sudo bash scripts/fix-permissions.sh www-data   # force a specific user

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

if [ "$(id -u)" -ne 0 ]; then
    echo "This script must be run as root (use: sudo bash scripts/fix-permissions.sh)" >&2
    exit 1
fi

WEB_USER="${1:-}"
if [ -z "$WEB_USER" ]; then
    for candidate in www-data apache nginx caddy; do
        if id "$candidate" >/dev/null 2>&1; then
            WEB_USER="$candidate"
            break
        fi
    done
fi
if [ -z "$WEB_USER" ]; then
    echo "Could not auto-detect the web server user (checked www-data, apache, nginx, caddy)." >&2
    echo "Re-run with the correct user: sudo bash scripts/fix-permissions.sh <user>" >&2
    exit 1
fi

echo "Project root : $PROJECT_ROOT"
echo "Web user     : $WEB_USER"

chown -R "$WEB_USER":"$WEB_USER" "$PROJECT_ROOT"

find "$PROJECT_ROOT" -type d -exec chmod 755 {} \;
find "$PROJECT_ROOT" -type f -exec chmod 644 {} \;

# Writable trees: uploaded files, generated logs, session-adjacent runtime data.
chmod -R 775 "$PROJECT_ROOT/storage" "$PROJECT_ROOT/public/uploads"

if [ -f "$PROJECT_ROOT/.env" ]; then
    chmod 640 "$PROJECT_ROOT/.env"
fi

echo "Done. public/uploads and storage are now owned by '$WEB_USER' and group/world-writable (775)."
echo "Verify with: ls -la $PROJECT_ROOT/public/uploads $PROJECT_ROOT/public/uploads/students"
