# School Management System — Build Checklist

A practical, phased roadmap. The base framework (Phases 0–2) is already implemented; everything below it is a guide for the rest of the build.

Legend: `[x]` done in this scaffold · `[ ]` to do · `[~]` partial / starter only

---

## Phase 0 — Project foundation
- [x] Project layout (`app/`, `config/`, `database/`, `public/`, `storage/`)
- [x] Front controller (`public/index.php`) + clean URLs (`.htaccess`)
- [x] PSR-4ish autoloader (no Composer required)
- [x] Config + `.env` loader (`config/config.php`)
- [x] Sessions, security headers, error handling
- [x] PDO database wrapper
- [x] Router with route params + middleware
- [x] Base controller, view engine, layouts (Bootstrap 5)
- [x] CSRF helper, output escaping helper
- [x] Flash messages
- [x] `.gitignore`, per-folder `.htaccess` denies

## Phase 1 — Authentication & access control
- [x] Login / logout
- [x] Password hashing (`password_hash` / `password_verify`)
- [x] Roles: admin, staff, student
- [x] Role-based route guards
- [x] Default admin seeded via installer
- [ ] "Forgot password" via email link
- [ ] User profile page (change own password / details)
- [ ] Account lockout after N failed attempts
- [ ] Two-factor auth (optional)

## Phase 2 — Core modules (CRUD)
- [x] Students (list, search, create, edit, delete)
- [x] Staff (linked login account, CRUD)
- [x] Classes (add / list / delete + student count)
- [x] Subjects (add / list / delete)
- [x] Attendance (per class, per day, present/absent/late)
- [x] Grades (per student / subject / term, auto letter grade)
- [x] Fees (billed / paid / balance, per student)
- [x] Announcements (post + feed)
- [x] Dashboard with KPIs and quick actions

## Phase 3 — Quality-of-life additions
- [ ] Pagination on all list pages
- [ ] Sortable / filterable tables (DataTables or HTMX)
- [ ] Bulk actions (e.g. promote class, archive students)
- [ ] CSV import for students/staff
- [ ] CSV export of any list
- [ ] Profile photos (file upload to `storage/uploads/`)
- [ ] Activity log / audit trail (who did what, when)
- [ ] Soft-deletes + restore for students/staff

## Phase 4 — Academic depth
- [x] Class–Subject assignment (teacher allocation, `teaching_assignments`)
- [x] HODs scoped strictly to their portal (`/hod`, `/marks`, `/reports`, `/announcements`)
- [x] HOD-only read-only "Students by class" view (`/hod/students`)
- [x] Three terms per academic year, strictly enforced on every mark-entry path
  - period chooser screen blocks `/marks`, `/marks/entry`, `/marks/department`
    until both academic year and term are explicitly selected
  - academic year is a validated dropdown (`YYYY/YYYY`, current ± 2)
- [x] Form 1 & Form 2 take all subjects; Form 3 & Form 4 specialise
  - per-student `stream` column (`none` / `science` / `arts`)
  - stream selector on the student form (required only for Form 3/4)
  - per-subject mark entry filters Form 3/4 students to the matching stream
  - department matrix mark entry filters Form 3/4 to the matching stream
- [x] Form 3 & Form 4 ranked WITHIN their stream on report cards & class reports
- [x] Class report splits Form 3/4 into Science + Arts sections
- [x] Report card shows the student's stream and "Position in {stream} stream"
- [ ] Timetable per class
- [ ] Exams: create exam → schedule → enter scores → publish
- [x] Report cards (printable HTML with school header — single + class booklet)
- [ ] Per-subject teacher comments
- [ ] Promotions / repeats at term end

## Phase 5 — Finance & operations
- [ ] Fee structures per class / per term (templates)
- [ ] Receipts (PDF) and printable invoices
- [ ] Payment methods (cash, mobile money, card) + reconciliation
- [ ] Outstanding balance reminders (email / SMS)
- [ ] Expense tracking (basic accounting for the school)

## Phase 6 — Communication
- [ ] Email integration (PHPMailer + SMTP)
- [ ] SMS gateway integration (Africa's Talking / Twilio)
- [ ] In-app notifications for students/parents
- [ ] Parent portal (separate role + login)

## Phase 7 — Reporting & analytics
- [ ] Attendance trends (charts) per class / per student
- [ ] Grade distribution per subject / per term
- [ ] Fees collection vs outstanding
- [ ] Exportable monthly / termly reports
- [ ] Print-friendly views

## Phase 8 — Hardening & deploy
- [x] CSRF tokens on every form
- [x] Prepared statements everywhere
- [x] Output escaping
- [x] Sensitive folders blocked from web access
- [x] Sessions hardened (HttpOnly, SameSite, Secure when HTTPS)
- [ ] Force HTTPS in production (`.htaccess` redirect)
- [ ] Content-Security-Policy header tuned for the app
- [ ] Brute-force / rate limiting on `/login`
- [ ] Database backups (cron `mysqldump` to off-site storage)
- [ ] Error monitoring / log rotation
- [ ] Automated daily DB backup script

## Phase 9 — Deployment workflows
- [x] Works on XAMPP locally
- [x] Works on shared hosting (cPanel) — root `.htaccess` redirects to `/public`
- [x] One-shot installer (`public/install.php`)
- [ ] Git-based deploy (GitHub Actions → SFTP/SSH)
- [ ] Staging vs production `.env` separation
- [ ] Healthcheck endpoint (`/healthz`)
- [ ] Dockerfile + `docker-compose.yml` for portable hosting

## Phase 10 — Nice-to-haves
- [ ] Multi-school / multi-tenant support
- [ ] Localization (English / Swahili / French)
- [ ] Dark mode toggle
- [ ] Library module (books, lending)
- [ ] Hostel / boarding module
- [ ] Transport / bus routes module
- [ ] Mobile app (read-only API + Flutter / React Native)

---

## Acceptance test (smoke test after install)

1. Open `/install.php` → see all green ticks → delete the file.
2. Log in as `admin@school.local / admin123`.
3. Add a class (`Form 1A`), add a subject (`Mathematics`), add a student in that class.
4. Create a staff user and log in as them in a private window — confirm they cannot see `/staff`.
5. Mark attendance for the class today.
6. Record a grade for the student.
7. Add a fee record (admin) and confirm balance shows correctly.
8. Post an announcement and verify it shows on the dashboard.
9. Log out and confirm you're redirected to `/login`.
10. Change the admin password from the Staff page.
