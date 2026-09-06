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

## One-off steps for specific releases

Run these **once**, after the normal pull + migrate above.

### Academic years become plain years (2026/2027 → 2026)

Academic years are now stored and shown as a single calendar year. The
year pickers on Reports, Results, Marks and the Bursar module list
`2026`, `2027`, … instead of `2026/2027`.

**Your existing data is kept.** Step 4 above (`php database/migrate.php`)
relabels the year column in place — it never deletes, moves or re-keys a
row. Everything recorded under `2026/2027` (marks, computed results,
report cards, fee structures, student bills and their payments) stays on
the same rows and is simply filed under **2026** afterwards. Terms,
subjects, students and amounts are untouched.

The migration prints exactly what it did, one line per year, e.g.:

```
  ok  grades: '2026/2027' -> '2026' (18420 rows kept, nothing deleted)
  ok  grades row count unchanged (18420)
```

A row count that changes, or a line starting `!!`, means something needs
attention — see below. Re-running the migration is safe; once converted it
reports `already flat` and does nothing.

Take a database backup before pulling, as with any release:

```bash
mysqldump -u root -p ssdacmis > ~/ssdacmis-before-flat-years.sql
```

If the migration reports `!!` for a year, it means that database already
held both forms of the same year (for example `2026` *and* `2026/2027`)
for the same student/subject/period, which the unique keys forbid. It
converts every row it safely can, leaves the conflicting ones under their
old label, deletes nothing, and tells you how many. Reconcile those few
records, then run the migration again.

After migrating, open **Reports → Year** and confirm the period you use
(e.g. `2026`) shows the classes and marks you expect.

### Separate mid-term / end-of-term results

Results are now published per assessment stage. `migrate.php` adds the
`stage` column and defaults every existing row to `endterm` (which is what
those rows already were — mid + end combined). The **mid-term** set is only
written when a class's marks are next saved, so backfill it for existing
marks in one pass:

```bash
sudo -u www-data php scripts/resync_results.php
```

Until you run it, Results/Reports with **Assessment = Mid-term** will look
empty for periods whose marks were entered before the upgrade. Safe to
re-run at any time; it recomputes rather than duplicating.

## Uploads still failing after this?

See the "Logo or student passport-photo upload fails" row in `DEPLOYMENT.md`
§8 (Troubleshooting). In short:

```bash
ls -la /var/www/SSDACMIS/public/uploads /var/www/SSDACMIS/public/uploads/students
sudo bash /var/www/SSDACMIS/scripts/fix-permissions.sh
```
