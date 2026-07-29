# VPS Pull Guide

Use these commands on your VPS after you push changes to GitHub.

This project is a plain PHP + Apache (or nginx + php-fpm) app — there is no
Node.js process to restart, so **do not use `pm2`** here. "Restarting" just
means reloading the web server so it picks up the new files (PHP files are
read fresh on every request, so most changes need no restart at all).

## If the project already exists on VPS

```bash
# 1) Go to project folder
cd /var/www/SSDACMIS

# 2) Check branch and status (optional but recommended)
git status
git branch

# 3) Pull latest code from GitHub
git pull origin main

# 4) Apply any new database schema changes (idempotent — safe to re-run)
sudo -u www-data php database/migrate.php

# 5) Fix ownership + permissions — IMPORTANT for photo/logo uploads.
#    git pull never changes file ownership; if it drifts back to your SSH
#    user instead of the web server user, uploads will fail to write even
#    though everything else works fine. Safe to re-run every time.
sudo bash scripts/fix-permissions.sh

# 6) Reload the web server (picks up .htaccess/.user.ini changes; clears
#    opcache if enabled). Only needed if you changed .htaccess, .user.ini,
#    or the vhost — plain PHP file changes need no reload.
sudo systemctl reload apache2
# or, for nginx + php-fpm:
# sudo systemctl reload nginx && sudo systemctl reload php8.2-fpm
```

## If this is the first deployment on VPS

See `DEPLOYMENT.md` §3 (VPS / cloud server) for the full first-time setup,
including creating the database, `.env`, the Apache vhost, and HTTPS. Then
run `sudo bash scripts/fix-permissions.sh` once before first use.

## Quick one-liner for routine updates (existing project)

```bash
cd /var/www/SSDACMIS && git pull origin main \
  && sudo -u www-data php database/migrate.php \
  && sudo bash scripts/fix-permissions.sh
```

## Uploads still failing after this?

See the "Logo or student passport-photo upload fails" row in `DEPLOYMENT.md`
§8 (Troubleshooting). In short:

```bash
ls -la /var/www/SSDACMIS/public/uploads /var/www/SSDACMIS/public/uploads/students
sudo bash /var/www/SSDACMIS/scripts/fix-permissions.sh
```
