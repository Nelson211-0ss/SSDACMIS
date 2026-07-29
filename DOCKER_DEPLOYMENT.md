# Docker deployment guide

A focused, copy-pasteable guide for running **SSD-ACMIS — School Management System** with Docker — for local development or for a containerised production deployment. This complements [DEPLOYMENT.md](DEPLOYMENT.md) (which covers XAMPP, cPanel and bare-metal VPS); use this file if Docker is your preferred path.

1. [What you need](#0-what-you-need)
2. [Option A — DB-only container (fastest for local dev)](#1-option-a--db-only-container-fastest-for-local-dev)
3. [Option B — Fully containerised (app + db via Compose)](#2-option-b--fully-containerised-app--db-via-compose)
4. [Production Dockerfile (recommended for real deployments)](#3-production-dockerfile-recommended-for-real-deployments)
5. [Environment variables](#4-environment-variables)
6. [First-run tasks](#5-first-run-tasks)
7. [Updating](#6-updating)
8. [Backups & restore](#7-backups--restore)
9. [Troubleshooting](#8-troubleshooting)
10. [Security checklist](#9-security-checklist)

---

## 0. What you need

- **Docker Engine** 24+ and the **Compose plugin** (`docker compose version` should work). Docker Desktop on macOS/Windows/Linux bundles both.
- The project has **zero Composer/npm dependencies**, so no build step beyond PHP extensions.
- Required PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` (validates uploaded image MIME types for logo/photo uploads). `gd` is installed below too but isn't currently called by the app — harmless to keep, safe to drop if you're trimming the image. These are added in the container, not on your host.

---

## 1. Option A — DB-only container (fastest for local dev)

If you already have PHP 8.1+ installed on your machine (or run it via XAMPP) but don't want to install MySQL/MariaDB system-wide, run **only the database** in Docker and use PHP's built-in server for the app. This is the quickest path and avoids touching system packages at all.

```bash
# 1. Start a MariaDB container, publish it on host port 3307
#    (avoids clashing with any MySQL already using 3306)
docker run -d --name ssdacmis-mysql \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=ssdacmis \
  -p 3307:3306 \
  mariadb:10.11

# 2. Wait for it to accept connections
until docker exec ssdacmis-mysql mysqladmin ping -uroot -proot --silent; do sleep 1; done

# 3. Configure .env to point at the container
cp .env.example .env
# Edit .env:
#   DB_HOST=127.0.0.1
#   DB_PORT=3307
#   DB_USER=root
#   DB_PASS=root
#   APP_URL=http://localhost:8000

# 4. Import schema + seed default accounts
docker exec -i ssdacmis-mysql mysql -uroot -proot ssdacmis < database/schema.sql
php database/migrate.php

# 5. Run the app with PHP's built-in server
php -S 0.0.0.0:8000 -t public
```

Visit `http://localhost:8000/login` — sign in with `admin@school.local` / `admin123` (see [§5](#5-first-run-tasks) for the full account list and next steps).

To stop/reuse later:

```bash
docker stop ssdacmis-mysql      # stop, keep data
docker start ssdacmis-mysql     # resume later, same data
docker rm -f ssdacmis-mysql     # remove permanently (data lost)
```

---

## 2. Option B — Fully containerised (app + db via Compose)

For a setup that runs everywhere Docker runs, without any host PHP/MySQL install, use Compose to run the app in an Apache+PHP container alongside a MariaDB container.

### 2.1 `docker-compose.yml`

Place this at the project root:

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
      APP_ENV: local
      APP_DEBUG: "true"
      APP_URL: http://localhost:8080
      DB_HOST: db
      DB_PORT: 3306
      DB_NAME: ssdacmis
      DB_USER: ssdacmis
      DB_PASS: ssdacmis
    depends_on:
      db:
        condition: service_healthy
    command: bash -c "
      docker-php-ext-install pdo_mysql gd &&
      a2enmod rewrite &&
      apache2-foreground"

  db:
    image: mariadb:10.11
    environment:
      MARIADB_DATABASE: ssdacmis
      MARIADB_USER: ssdacmis
      MARIADB_PASSWORD: ssdacmis
      MARIADB_ROOT_PASSWORD: rootsecret
    volumes:
      - db-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 10

volumes:
  db-data:
```

> Note: `db` is **not** exposed on a host port here — the `app` container reaches it over the internal Compose network at hostname `db`. Only add a `ports:` entry under `db` if you need to connect from outside Docker (e.g. a GUI DB client on your host).

### 2.2 `docker/000-default.conf`

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html/public
    <Directory /var/www/html/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 2.3 Bring it up

```bash
cp .env.example .env
# Edit .env to match the compose environment above:
#   DB_HOST=db, DB_PORT=3306, DB_NAME=ssdacmis, DB_USER=ssdacmis, DB_PASS=ssdacmis
#   APP_URL=http://localhost:8080

docker compose up -d
docker compose logs -f app     # watch for "apache2-foreground" ready message, Ctrl+C to stop tailing

# Import schema + seed accounts
docker compose exec -T db mysql -uroot -prootsecret ssdacmis < database/schema.sql
docker compose exec app php database/migrate.php
```

Visit `http://localhost:8080/login`. Because the code is **bind-mounted** (`.:/var/www/html`), edits on your host show up immediately — this mode is meant for development, not production (see next section).

---

## 3. Production Dockerfile (recommended for real deployments)

For production, bake the code and PHP extensions into an **immutable image** instead of installing extensions on every container start and bind-mounting source. This is faster to boot, reproducible, and lets you version/tag releases.

### 3.1 `Dockerfile`

```dockerfile
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql gd \
    && a2enmod rewrite headers expires

COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/public/uploads \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod -R 775 /var/www/html/storage /var/www/html/public/uploads

# .env is provided at runtime, never baked into the image
```

Add a `.dockerignore` so secrets and local artefacts don't end up in the image:

```
.env
.git
storage/logs/*
public/uploads/*
!public/uploads/students/.gitkeep
```

### 3.2 `docker-compose.prod.yml`

```yaml
services:
  app:
    build: .
    image: ssdacmis:latest
    restart: unless-stopped
    env_file: .env
    ports:
      - "80:80"
    depends_on:
      db:
        condition: service_healthy
    volumes:
      - uploads-data:/var/www/html/public/uploads
      - storage-data:/var/www/html/storage

  db:
    image: mariadb:10.11
    restart: unless-stopped
    environment:
      MARIADB_DATABASE: ssdacmis
      MARIADB_USER: ssdacmis
      MARIADB_PASSWORD_FILE: /run/secrets/db_password
      MARIADB_ROOT_PASSWORD_FILE: /run/secrets/db_root_password
    secrets:
      - db_password
      - db_root_password
    volumes:
      - db-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 10

secrets:
  db_password:
    file: ./secrets/db_password.txt
  db_root_password:
    file: ./secrets/db_root_password.txt

volumes:
  db-data:
  uploads-data:
  storage-data:
```

Put an HTTPS-terminating reverse proxy (nginx, Traefik, or Caddy) in front of the `app` service in production rather than exposing port 80 directly — Let's Encrypt integrates cleanly with any of the three.

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec -T db \
  mysql -uroot -p"$(cat secrets/db_root_password.txt)" ssdacmis < database/schema.sql
docker compose -f docker-compose.prod.yml exec app php database/migrate.php
```

---

## 4. Environment variables

Same keys as [`.env.example`](.env.example); the values differ only in `DB_HOST` (a Compose service name instead of an IP) and `APP_URL` (the published port):

| Key | Dev (Option A) | Dev (Option B) | Production |
|---|---|---|---|
| `APP_ENV` | `local` | `local` | `production` |
| `APP_DEBUG` | `true` | `true` | `false` |
| `APP_URL` | `http://localhost:8000` | `http://localhost:8080` | `https://yourdomain.com` |
| `DB_HOST` | `127.0.0.1` | `db` | `db` |
| `DB_PORT` | `3307` (mapped) | `3306` | `3306` |
| `DB_USER` / `DB_PASS` | `root` / `root` | `ssdacmis` / `ssdacmis` | from `secrets/` files, not `.env` |

`.env` is never baked into an image or committed — mount/inject it at runtime (`env_file:` in Compose, or your orchestrator's secret store).

---

## 5. First-run tasks

Whichever option you used:

1. Sign in as admin (`admin@school.local` / `admin123`) and **change the password immediately** (Staff page or Settings).
2. Also change the seeded HOD (`hod@school.local` / `hod123`) and bursar (`bursar@school.local` / `bursar123`) passwords.
3. If you used the browser installer (`public/install.php`) instead of importing `schema.sql` via CLI, **delete it** afterwards:
   ```bash
   docker compose exec app rm public/install.php
   ```
4. Continue with **§5 First-run configuration** in [DEPLOYMENT.md](DEPLOYMENT.md#5-first-run-configuration-mandatory-after-install) — school identity, subjects, classes, staff, HODs.

---

## 6. Updating

```bash
git pull origin main

# Option A (DB-only container): just restart PHP, migrate as usual
php database/migrate.php

# Option B / production (Compose):
docker compose build app          # rebuild image with new code (skip for Option B's bind-mount dev setup)
docker compose up -d
docker compose exec app php database/migrate.php
```

`migrate.php` is idempotent — safe to re-run on every deploy.

---

## 7. Backups & restore

```bash
# Dump the database (works for any option — adjust container/user names)
docker exec ssdacmis-mysql mysqldump --single-transaction -uroot -proot ssdacmis \
  | gzip > backups/ssdacmis-$(date +%F).sql.gz

# Back up the uploads volume
docker run --rm -v ssdacmis_uploads-data:/data -v "$PWD/backups":/backup \
  alpine tar czf /backup/uploads-$(date +%F).tgz -C /data .

# Restore
gunzip < backups/ssdacmis-2026-07-29.sql.gz | docker exec -i ssdacmis-mysql mysql -uroot -proot ssdacmis
```

---

## 8. Troubleshooting

| Symptom | Likely cause & fix |
|---|---|
| `docker: Cannot connect to the Docker daemon` | Docker Desktop / the daemon isn't running. On Linux with Docker Desktop: `systemctl --user start docker-desktop`, then wait a few seconds and retry `docker ps`. |
| App container can't reach the DB (`SQLSTATE[HY000] [2002] Connection refused`) | `DB_HOST` must be the Compose **service name** (`db`), not `127.0.0.1`, when both run in Compose. If the DB container is still starting, wait for its healthcheck (`docker compose ps`). |
| `Uploads folder could not be created` / permission denied writing to `public/uploads` | With a bind mount (Option B dev), the container's `www-data` UID may not match your host user's file ownership. Fix on the host: `chmod -R 777 public/uploads storage` (dev only), or in production bake ownership into the image (§3.1) and use named volumes instead of bind mounts. |
| Port already in use (`8000`, `8080`, `3306`, `3307`) | Something else is bound to that port. Change the left side of the `-p`/`ports:` mapping, e.g. `-p 3308:3306`. |
| Changes to PHP code don't show up | Option B's dev compose bind-mounts source, so edits are live — restart isn't needed. In the production setup (§3), code is baked into the image; rebuild (`docker compose build app`) after every code change. |
| `migrate.php says "Unknown database"` | The database was never created. Re-check the `MARIADB_DATABASE` / `MYSQL_DATABASE` env var on the `db` container, or create it manually: `docker exec -it <db-container> mysql -uroot -p<pass> -e "CREATE DATABASE ssdacmis"`. |

---

## 9. Security checklist

- [ ] Default admin/HOD/bursar passwords changed.
- [ ] `APP_DEBUG=false`, `APP_ENV=production` in the production `.env`.
- [ ] `public/install.php` deleted (or never used — schema imported via CLI instead).
- [ ] DB container's port is **not** published to the host/internet in production (no `ports:` under `db` unless you need external DB access).
- [ ] DB root/app passwords come from Docker secrets or an external secret manager, not plaintext in `docker-compose.yml` or a committed `.env`.
- [ ] `.env` and `secrets/*.txt` are in `.gitignore` and `.dockerignore`.
- [ ] A reverse proxy terminates HTTPS in front of the app container.
- [ ] Named volumes (`db-data`, `uploads-data`) are included in your backup routine.
