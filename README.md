# SSD-ACMIS — School Management System

A lightweight PHP + MySQL school management framework designed to run on **XAMPP** locally and deploy cleanly to any **shared host (cPanel)** or cloud VM. No Composer required.

> **Folder name** — this project ships in a folder named `SSDACMIS/` (older clones may still be named `schoolreg/`). The folder name is detected at runtime via `dirname($_SERVER['SCRIPT_NAME'])`, so renaming the folder does **not** require any code changes; URLs, asset paths and redirects all auto-adapt. The MySQL database name and session cookie name are independent of the folder name and may stay `schoolreg`/`schoolreg_sid` even after a rebrand.

## Stack

- **PHP 8+** (PDO, sessions)
- **MySQL / MariaDB**
- **Bootstrap 5 + Bootstrap Icons** (via CDN)
- Front-controller MVC, custom router, PSR-4ish autoloader (zero external dependencies)

## Roles

| Role    | Capabilities                                                                |
|---------|-----------------------------------------------------------------------------|
| Admin   | Everything: manage staff, students, classes, subjects, fees, settings       |
| Staff   | Manage students, classes, subjects, attendance, grades, announcements       |
| Student | View own grades, fees, announcements                                        |

## Modules

- Authentication (login / logout, role-based access, CSRF, hashed passwords)
- Dashboard with KPIs and quick actions
- Students (CRUD + search)
- Staff (CRUD + linked login account)
- Classes & Subjects
- Attendance (per class / per day)
- Grades (per student / subject / term, auto letter grade)
- Fees (billed, paid, balance)
- Announcements

## Project layout

```
SSDACMIS/
├── app/
│   ├── Controllers/        HTTP request handlers
│   ├── Core/               Framework: App, Router, Database, Auth, View, Flash
│   ├── Models/             Data access (PDO)
│   ├── Views/              PHP templates (layouts/ + per-module folders)
│   ├── bootstrap.php       Autoloader + boot
│   └── routes.php          Route table
├── config/
│   └── config.php          App + DB config (reads .env if present)
├── database/
│   └── schema.sql          MySQL schema + seed data
├── public/                 ← Web root
│   ├── index.php           Front controller
│   ├── install.php         One-shot installer (delete after use)
│   ├── .htaccess           Pretty URLs
│   └── assets/             css / js / img
├── storage/                Logs + uploads (web-blocked)
├── .env.example            Copy to .env for your environment
└── README.md / CHECKLIST.md
```

---

## 1. Local setup with XAMPP

1. **Install XAMPP** (PHP 8+).
2. Drop this folder into `XAMPP/htdocs/`, e.g. `htdocs/SSDACMIS/`.
3. Start **Apache** and **MySQL** from the XAMPP Control Panel.
4. (Optional but recommended) Copy `.env.example` to `.env` and adjust if your MySQL user/password differ from `root` / empty:
   ```bash
   cp .env.example .env
   ```
5. Open the installer in your browser:
   ```
   http://localhost/SSDACMIS/public/install.php
   ```
   This will create the database, import the schema, and create the default admin user.
6. **Delete `public/install.php`** once you see the success page.
7. Log in:
   ```
   http://localhost/SSDACMIS/public/login
   Email:    admin@school.local
   Password: admin123
   ```
   Change this password immediately from the **Staff** page.

> If you prefer doing it manually, open phpMyAdmin and import `database/schema.sql`, then run the installer once just to create the admin.

---

## 2. Deploy online (shared hosting / cPanel)

1. **Create a MySQL database** in cPanel and note the host, database name, user, and password.
2. **Upload the project** via FTP/SFTP or the cPanel File Manager.
3. **Point the domain's document root to `/public/`** (preferred — most secure).
   - cPanel → *Domains* → edit your domain → set Document Root to `.../SSDACMIS/public`.
   - If your host won't let you change the document root, the included root `.htaccess` will silently forward all requests into `/public/`.
4. **Create `.env`** in the project root with production values:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   APP_KEY=<paste a long random string>

   DB_HOST=localhost
   DB_NAME=yourcpaneluser_ssdacmis
   DB_USER=yourcpaneluser_dbuser
   DB_PASS=strong-password
   ```
5. Visit `https://yourdomain.com/install.php` once to import the schema and create the admin.
6. **DELETE `public/install.php`** from the server immediately after.
7. Make sure the `storage/` directory is writable by the web user (usually 755 is fine; 775 if needed).

### Deploy on a VPS / cloud (Ubuntu + Apache)

```bash
sudo apt install apache2 php php-mysql mariadb-server
sudo a2enmod rewrite
# Set DocumentRoot to /var/www/SSDACMIS/public and AllowOverride All
mysql -u root -p < database/schema.sql
# Create .env, then:
php -S 0.0.0.0:8000 -t public   # quick test
```

---

## 3. URLs

| Path                  | Who         | Purpose                          |
|-----------------------|-------------|----------------------------------|
| `/login`              | Public      | Sign in                          |
| `/dashboard`          | Any logged in | KPIs, quick actions            |
| `/students`           | Admin/Staff | List + search                    |
| `/students/create`    | Admin/Staff | New student                      |
| `/staff`              | Admin       | Manage staff                     |
| `/classes`            | Admin/Staff | View; admin can add/delete       |
| `/subjects`           | Admin/Staff | View; admin can add/delete       |
| `/attendance`         | Admin/Staff | Daily roll call                  |
| `/grades`             | Any logged in | Students see own; staff record |
| `/fees`               | Any logged in | Students see own; admin record |
| `/announcements`      | Any logged in | Read; staff/admin post         |

---

## 4. Security baseline

- All forms are CSRF-protected
- Passwords are hashed with `password_hash` (bcrypt)
- PDO prepared statements everywhere — no raw concatenation
- Sessions are `HttpOnly`, `SameSite=Lax`, `Secure` when HTTPS is detected
- Sensitive directories (`app/`, `config/`, `database/`, `storage/`) are blocked via `.htaccess`
- Output is escaped with `View::e()` (htmlspecialchars)

## 5. Where to extend

- **New module?** Add a controller in `app/Controllers/`, optional model in `app/Models/`, views in `app/Views/<module>/`, and routes in `app/routes.php`.
- **Send emails?** Drop a `Mailer` class into `app/Core/` (PHPMailer works well; install via Composer if you want).
- **Reports / PDF?** Add a route that renders an HTML view with `?print=1` styles, or integrate Dompdf.

See `CHECKLIST.md` for the full roadmap.
