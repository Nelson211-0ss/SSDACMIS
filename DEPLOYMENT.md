# Deployment guide

End-to-end, copy-pasteable steps for getting **SSD-ACMIS — School Management System** running:

> **Folder name** — examples in this guide assume the project lives in a folder named `SSDACMIS/`. Older copies may still be named `schoolreg/`; the runtime detects the install path automatically (`dirname($_SERVER['SCRIPT_NAME'])`), so either folder name works without code changes. The MySQL database name and session cookie name are independent — you can keep `schoolreg`/`schoolreg_sid` or rename them. Examples below use `ssdacmis` for new installs.

1. [Local development with XAMPP](#1-local-development-with-xampp) (macOS / Windows / Linux)
2. [Online — Shared hosting (cPanel)](#2-online--shared-hosting-cpanel)
3. [Online — VPS / cloud server (Ubuntu + Apache)](#3-online--vps--cloud-server-ubuntu--apache)
4. [Online — Docker (optional)](#4-online--docker-optional)
5. [First-run configuration (mandatory after install)](#5-first-run-configuration-mandatory-after-install)
6. [Updating an existing deployment](#6-updating-an-existing-deployment)
7. [Backups & restore](#7-backups--restore)
8. [Troubleshooting](#8-troubleshooting)

---

## 0. What you need (every environment)

| Component   | Minimum     | Recommended | Notes                                                |
|-------------|-------------|-------------|------------------------------------------------------|
| PHP         | **8.1**     | 8.2 / 8.3   | Extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` (logo upload) |
| MySQL       | 5.7         | 8.0         | MariaDB 10.4+ also works                              |
| Web server  | Apache 2.4  | Apache 2.4  | nginx works too — see VPS section                     |
| Disk        | ~50 MB code + uploads | 1 GB free | Logos, future media                              |
| Composer    | _not required_ |          | The framework has zero external dependencies          |

You also need:

- A **database name**, **DB user** and **password** (you'll create these below).
- For online deployments: a **domain name** (or sub-domain) and ideally **HTTPS** (free via Let's Encrypt).

---

## 1. Local development with XAMPP

Tested on macOS (matches your machine), but the same steps work on Windows and Linux.

### 1.1 Install XAMPP

1. Download XAMPP for your OS from <https://www.apachefriends.org>.
2. Install it. The default install paths are:
   - macOS: `/Applications/XAMPP/`
   - Windows: `C:\xampp\`
   - Linux: `/opt/lampp/`
3. Launch the **XAMPP Control Panel / manager-osx** and start **Apache** and **MySQL**.

### 1.2 Drop the project into `htdocs`

The project must live inside XAMPP's web root:

| OS      | Target folder                                     |
|---------|---------------------------------------------------|
| macOS   | `/Applications/XAMPP/xamppfiles/htdocs/SSDACMIS` |
| Windows | `C:\xampp\htdocs\SSDACMIS`                       |
| Linux   | `/opt/lampp/htdocs/SSDACMIS`                     |

If you cloned with git, do it directly:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs
git clone <your-repo-url> SSDACMIS
```

### 1.3 Create the database

Open <http://localhost/phpmyadmin> and:

1. Click **New** in the left sidebar.
2. Name the database `ssdacmis` (or keep `schoolreg` if you're upgrading an existing install), choose collation `utf8mb4_unicode_ci`, click **Create**.

(Or via the terminal:)

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root -e \
  "CREATE DATABASE IF NOT EXISTS ssdacmis DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 1.4 Configure `.env`

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/SSDACMIS
cp .env.example .env
```

For a default XAMPP install you usually do **not** need to edit anything — `root` with empty password is the default. If you set a MySQL root password in XAMPP, edit `.env` and update `DB_PASS`.

### 1.5 Run the installer (creates tables + seeds admin)

Open in your browser:

```
http://localhost/SSDACMIS/public/install.php
```

You should see green "OK" lines confirming:

- DB connection
- `database/schema.sql` imported
- Default admin `admin@school.local / admin123` created

### 1.6 Apply the latest migrations

The schema has been extended several times after `schema.sql` was first written (HOD portal, school motto/logo, subject offering flags, HOD admin accounts). The migration script applies all of those idempotently:

```
http://localhost/SSDACMIS/database/migrate.php
```

You should see lines starting with `ok` (changes applied) or `--` (already up to date). Re-running is safe.

### 1.7 **Delete `public/install.php`**

```bash
rm /Applications/XAMPP/xamppfiles/htdocs/SSDACMIS/public/install.php
```

This is important — the installer is a one-shot setup tool and must not be reachable on a live system.

### 1.8 Sign in

| URL                                           | Default credentials                |
|-----------------------------------------------|------------------------------------|
| <http://localhost/SSDACMIS/public/login>      | `admin@school.local` / `admin123`  |
| <http://localhost/SSDACMIS/public/hod/login>  | `hod@school.local` / `hod123`      |

> **Change both passwords immediately** (Admin → HODs for the HOD account, Admin → Settings page for the admin profile, or by editing the user record).

Now jump to [§5 First-run configuration](#5-first-run-configuration-mandatory-after-install).

---

## 2. Online — Shared hosting (cPanel)

This is the easiest "real world" deployment for school IT — no server admin required.

### 2.1 Create the database in cPanel

1. cPanel → **MySQL Databases**.
2. Create a database — note its full name, e.g. `mysite_ssdacmis`.
3. Create a database user — note its full name, e.g. `mysite_appuser`, and a strong password.
4. **Add the user to the database** with **ALL PRIVILEGES**.

### 2.2 Upload the project

Two options:

**Option A — File Manager / zip (simplest):**

1. On your laptop, zip the project folder: `SSDACMIS.zip` (exclude `.env` and `node_modules`/`vendor` if present).
2. cPanel → **File Manager** → upload `SSDACMIS.zip` into your home directory (e.g. `/home/mysite/`).
3. Right-click the zip → **Extract**. You should now have `/home/mysite/SSDACMIS/`.

**Option B — Git (if cPanel has Git Version Control):**

cPanel → **Git Version Control** → **Create** → clone your repo into `/home/mysite/SSDACMIS`.

### 2.3 Point the domain at `/public`

The most secure option (and what we strongly recommend):

1. cPanel → **Domains** → edit your domain (or sub-domain like `school.mysite.com`).
2. Set **Document Root** to `/home/mysite/SSDACMIS/public`.
3. Save.

If your host won't let you change the document root, the project's root-level `.htaccess` automatically forwards every request into `/public/` — so things will still work, just less hardened.

### 2.4 Create the production `.env`

In cPanel File Manager, navigate to `SSDACMIS/` (the project root, NOT `public/`) and create a file called `.env` with:

```env
APP_NAME="Your School Name"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_TZ=Africa/Nairobi
APP_KEY=<paste a long random string — e.g. 32+ chars>

DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=mysite_ssdacmis
DB_USER=mysite_appuser
DB_PASS=<the strong password you set>

SESSION_NAME=ssdacmis_sid
SESSION_LIFETIME=7200
```

Generate a random `APP_KEY` quickly with any of these:

```bash
# macOS / Linux:
openssl rand -base64 48

# Or in a browser console:
crypto.randomUUID() + crypto.randomUUID()
```

### 2.5 Run the installer

Visit (replace with your real domain):

```
https://yourdomain.com/install.php
```

If you didn't change the document root and are using the root `.htaccess` fallback, the URL is:

```
https://yourdomain.com/public/install.php
```

Wait for the green "OK" lines.

### 2.6 Apply the latest migrations

```
https://yourdomain.com/../database/migrate.php
```

Specifically — depending on your document root:

- DocumentRoot is `/public` → **`https://yourdomain.com/database/migrate.php` will 404** because `/database/` is outside the web root. Run it instead from cPanel's **Terminal** (or SSH):

  ```bash
  cd ~/SSDACMIS
  php database/migrate.php
  ```

- DocumentRoot is the project root → use the URL form: `https://yourdomain.com/database/migrate.php` (the root `.htaccess` blocks `.env` etc., but `migrate.php` is intentionally left reachable).

You should see `ok` lines for each schema change applied.

### 2.7 **Delete the installer**

```bash
rm ~/SSDACMIS/public/install.php
```

(or right-click → Delete in File Manager).

### 2.8 Set permissions

Most cPanel hosts already do this for you, but make sure:

```bash
cd ~/SSDACMIS
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R 775 storage public/uploads
```

### 2.9 Sign in & secure the defaults

Same defaults as in §1.8. Change both passwords immediately.

---

## 3. Online — VPS / cloud server (Ubuntu + Apache)

Tested on Ubuntu 22.04 LTS. The same approach works on Debian 11+.

### 3.1 Provision a server

DigitalOcean / Linode / AWS Lightsail / Hetzner / Contabo — any small VPS (1 GB RAM is plenty) works. Open ports **80** and **443**.

### 3.2 Install the LAMP stack

```bash
sudo apt update
sudo apt install -y apache2 \
    php php-cli php-mysql php-mbstring php-gd php-xml php-zip php-fileinfo php-curl \
    libapache2-mod-php \
    mariadb-server unzip git

sudo a2enmod rewrite headers expires
sudo systemctl enable --now apache2 mariadb
```

### 3.3 Secure MariaDB and create the database

```bash
sudo mysql_secure_installation
```

Answer **Y** to: set root password, remove anonymous users, disallow remote root, drop test DB, reload privileges.

Now create the application's database + user:

```bash
sudo mariadb <<'SQL'
CREATE DATABASE ssdacmis DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ssdacmis'@'localhost' IDENTIFIED BY 'CHANGE_ME_strong_password';
GRANT ALL PRIVILEGES ON ssdacmis.* TO 'ssdacmis'@'localhost';
FLUSH PRIVILEGES;
SQL
```

### 3.4 Drop the project on the server

```bash
sudo mkdir -p /var/www
sudo chown -R $USER:$USER /var/www
cd /var/www
git clone <your-repo-url> SSDACMIS
# Or upload via rsync from your laptop:
# rsync -avz --exclude '.env' --exclude '.git' ./ user@server:/var/www/SSDACMIS/
```

### 3.5 Create the `.env`

```bash
cd /var/www/SSDACMIS
cp .env.example .env
nano .env
```

Set production values (same shape as in §2.4): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://yourdomain.com`, a real `APP_KEY`, and the DB credentials you just created.

### 3.6 Set ownership + permissions

```bash
sudo chown -R www-data:www-data /var/www/SSDACMIS
sudo find /var/www/SSDACMIS -type d -exec chmod 755 {} \;
sudo find /var/www/SSDACMIS -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/SSDACMIS/storage /var/www/SSDACMIS/public/uploads
sudo chmod 640 /var/www/SSDACMIS/.env
```

### 3.7 Configure the Apache vhost

```bash
sudo tee /etc/apache2/sites-available/ssdacmis.conf >/dev/null <<'CONF'
<VirtualHost *:80>
    ServerName  yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/SSDACMIS/public

    <Directory /var/www/SSDACMIS/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/ssdacmis-error.log
    CustomLog ${APACHE_LOG_DIR}/ssdacmis-access.log combined
</VirtualHost>
CONF

sudo a2dissite 000-default.conf
sudo a2ensite ssdacmis.conf
sudo apache2ctl configtest && sudo systemctl reload apache2
```

### 3.8 Run the installer + migrations

Visit `https://yourdomain.com/install.php` (assumes DocumentRoot is `/public`).

Then apply migrations (note: `database/` is outside the web root with this setup, so use the CLI):

```bash
cd /var/www/SSDACMIS
sudo -u www-data php database/migrate.php
```

### 3.9 Delete the installer

```bash
sudo rm /var/www/SSDACMIS/public/install.php
```

### 3.10 Add HTTPS (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
```

Certbot rewrites the vhost for SSL automatically. After it finishes:

```bash
sudo systemctl reload apache2
# Auto-renewal is already a systemd timer; verify:
sudo systemctl list-timers | grep certbot
```

### 3.11 Optional: nginx instead of Apache

If you prefer nginx + php-fpm, the relevant server block looks like:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/SSDACMIS/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Block sensitive folders if document root is the project root by mistake
    location ~ ^/(app|config|database|storage|\.env) { deny all; }
}
```

---

## 4. Online — Docker (optional)

If you'd rather containerise everything, drop these files at the project root and run.

### 4.1 `docker-compose.yml`

```yaml
services:
  app:
    image: php:8.3-apache
    working_dir: /var/www/html
    volumes:
      - .:/var/www/html
      - ./docker/000-default.conf:/etc/apache2/sites-available/000-default.conf:ro
    ports:
      - "8080:80"
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      APP_URL: http://localhost:8080
      DB_HOST: db
      DB_NAME: ssdacmis
      DB_USER: ssdacmis
      DB_PASS: ssdacmis
    depends_on: [db]
    command: bash -c "
      docker-php-ext-install pdo_mysql gd &&
      a2enmod rewrite &&
      apache2-foreground"

  db:
    image: mariadb:11
    environment:
      MARIADB_DATABASE: ssdacmis
      MARIADB_USER: ssdacmis
      MARIADB_PASSWORD: ssdacmis
      MARIADB_ROOT_PASSWORD: rootsecret
    volumes:
      - db-data:/var/lib/mysql
    ports:
      - "3306:3306"

volumes:
  db-data:
```

### 4.2 `docker/000-default.conf`

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html/public
    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 4.3 Bring it up

```bash
docker compose up -d
# Wait ~10s for MariaDB to start, then:
docker compose exec app php database/migrate.php

# Visit http://localhost:8080/install.php once, then:
docker compose exec app rm public/install.php
```

---

## 5. First-run configuration (mandatory after install)

Sign in as admin and walk through the following in order. Each step takes ~1 minute.

1. **Settings → School identity** — set your **School name**, **Motto**, upload the **Logo**, and pick an accent colour. These show up on the sidebar, login pages, and report card headers.
2. **Settings → Change your password** — replace the seed `admin123` immediately.
3. **Subjects → Curriculum** — tick which subjects your school actually teaches (the rest disappear from mark entry and report cards).
4. **Classes** — confirm Form 1A through Form 4A exist (or rename / add streams as needed). Set the class teacher per class.
5. **Staff** — create the teaching staff. Each gets a login they can use at `/login`.
6. **HODs** (admin sidebar → **HODs**) — create one HOD per department: name, department label, email, password. They sign in at `/hod/login` and have full mark-entry powers across Forms 1–4.
7. **Students** — admit students; the admission number is auto-generated from the class prefix (e.g. `F1A001`).
8. **Test the full flow**:
   - Sign in as a HOD → enter a few marks → save.
   - Open the student's report card → confirm marks render and the school logo / motto appear.
   - Print a class booklet (landscape A4) — it should hide the sidebar and look like an official document.

---

## 6. Updating an existing deployment

Whenever you pull new code from this repo onto a running site:

```bash
# 1. Take a backup (see §7) — always.

# 2. Pull / upload the new code.
cd /var/www/SSDACMIS && git pull origin main
# or rsync from your laptop, or upload via cPanel File Manager.

# 3. Apply any new schema changes (idempotent — safe to re-run).
sudo -u www-data php database/migrate.php
# Or visit https://yourdomain.com/database/migrate.php in a browser if /database/
# is inside the web root.

# 4. Clear stale opcache (only if APC/opcache is enabled and you don't see the new code):
sudo systemctl reload apache2
```

That's it — there is no compile step, no `composer install`, no `npm build`. The framework has zero external dependencies.

---

## 7. Backups & restore

**Daily database dump:**

```bash
mysqldump --single-transaction -u ssdacmis -p ssdacmis \
  | gzip > /backups/ssdacmis-$(date +%F).sql.gz
```

**Files (logos, uploaded badges):**

```bash
tar czf /backups/ssdacmis-uploads-$(date +%F).tgz \
  /var/www/SSDACMIS/public/uploads
```

**Automate** with a cron entry, e.g.:

```bash
sudo crontab -e
# Run nightly at 02:00:
0 2 * * * mysqldump --single-transaction -u ssdacmis -p'PASSWORD' ssdacmis | gzip > /backups/ssdacmis-$(date +\%F).sql.gz
```

**Restore:**

```bash
gunzip < /backups/ssdacmis-2026-04-26.sql.gz | mysql -u ssdacmis -p ssdacmis
```

---

## 8. Troubleshooting

| Symptom                                                              | Likely cause & fix                                                                                                                                      |
|----------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------|
| **500 Internal Server Error** on every page                          | Tail the web-server error log. On Apache: `sudo tail -f /var/log/apache2/ssdacmis-error.log`. Common: PHP extension missing, syntax error in `.env`.    |
| **"DB connection failed"** in the installer                          | Check `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` in `.env`. On cPanel hosts, the user/db names usually have a prefix like `mysite_`.                    |
| **Every URL 404s except `/`**                                        | `mod_rewrite` is off, or `AllowOverride All` is missing. On Ubuntu: `sudo a2enmod rewrite && sudo systemctl reload apache2`. Verify `.htaccess` was uploaded too. |
| **"Your session expired. Please try again."** on every form          | The session cookie is being dropped — usually a cookie-domain or HTTPS mix-up. Make sure `APP_URL` matches the URL you actually visit (https vs http).  |
| **Logo upload fails / "uploads/ not writable"**                      | `sudo chown -R www-data:www-data public/uploads && sudo chmod -R 775 public/uploads`.                                                                   |
| **`migrate.php` says "Unknown database '...'"** (whatever the name is) | The DB doesn't exist yet — create it (see §1.3 / §2.1 / §3.3). The migrate script does NOT create the database itself.                                |
| **"Cannot redeclare function env()"** when running CLI scripts       | You bootstrapped the app twice in the same process. Use `php database/migrate.php` directly — don't `require` it from another script.                   |
| **Reports render but the logo is missing**                           | The logo path is web-relative (e.g. `uploads/logo-123.png`). If your domain serves out of `/public`, that's correct. If you're rewriting from the project root, make sure `public/uploads/` is reachable. |
| **HOD created via /hods can't sign in**                              | They use **`/hod/login`**, not `/login`. The main login page is for admin/staff/students only and will reject HOD accounts with a friendly message.    |
| **Wrong timezone on reports / "Issued" date**                        | Set `APP_TZ` in `.env` (e.g. `Africa/Nairobi`, `UTC`, `Europe/London`).                                                                                  |

### Useful one-liners

```bash
# Show PHP version + loaded extensions:
php -v && php -m

# Tail Apache error log live:
sudo tail -f /var/log/apache2/error.log

# Test DB credentials from CLI:
mysql -u ssdacmis -p -h 127.0.0.1 ssdacmis -e "SELECT COUNT(*) AS users FROM users;"

# Re-run migrations safely:
php database/migrate.php
```

---

## 9. Security checklist before going live

- [ ] Default admin password changed (`admin@school.local`).
- [ ] Default HOD password changed (`hod@school.local`).
- [ ] `APP_DEBUG=false` and `APP_ENV=production` in `.env`.
- [ ] `APP_KEY` is a long random string (≥ 32 chars), unique per environment.
- [ ] `public/install.php` deleted from the server.
- [ ] `.env` has `chmod 640` (or 600), owned by the web user.
- [ ] Document root is `/public` (not the project root).
- [ ] HTTPS is on — visitors get redirected from http to https.
- [ ] A nightly DB backup is running (cron).
- [ ] You have an off-site copy of the latest backup.

You're done. Welcome aboard.
