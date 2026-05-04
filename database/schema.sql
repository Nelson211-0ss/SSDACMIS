-- ============================================================
-- SSD-ACMIS - School Management System - Database Schema
-- Import this file in phpMyAdmin (XAMPP) or via mysql CLI:
--   mysql -u root -p ssdacmis < database/schema.sql
--
-- Upgrading from the old 'schoolreg' database? Run the rename helper once
-- instead of re-importing this file:
--   php database/rename_to_ssdacmis.php
-- ============================================================

CREATE DATABASE IF NOT EXISTS ssdacmis
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE ssdacmis;

-- ---------- Users (login accounts: admin / staff / student) ----------
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(190) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','staff','student','hod','bursar') NOT NULL DEFAULT 'staff',
    -- Free-text department label shown next to HOD accounts (e.g. "Sciences",
    -- "Humanities"). Informational only — for ROLE='hod' users this is the
    -- "wing" the HOD heads, but does NOT restrict mark-entry access (any HOD
    -- can enter marks for any subject across Forms 1–4).
    department  VARCHAR(60)  NULL,
    status      ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ---------- Classes ----------
-- admission_prefix is the short code prepended to auto-generated student
-- admission numbers (e.g. 'F1A' -> 'F1A001'). Auto-derived from name on
-- create; admin can override.
CREATE TABLE IF NOT EXISTS classes (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(100) NOT NULL,
    level            VARCHAR(50)  NULL,
    admission_prefix VARCHAR(10)  NOT NULL DEFAULT '',
    class_teacher_id INT UNSIGNED NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_class_name (name),
    KEY idx_class_teacher (class_teacher_id)
) ENGINE=InnoDB;

-- ---------- Subjects ----------
-- category: core | science | arts | optional (used for grouped report sections)
CREATE TABLE IF NOT EXISTS subjects (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    code        VARCHAR(30)  NULL,
    category    VARCHAR(20)  NOT NULL DEFAULT 'optional',
    -- The school admin curates which subjects this school actually teaches.
    -- Anything with is_offered=0 is hidden from mark entry, dashboards and
    -- report cards, but historic grades are kept (no destructive delete).
    is_offered  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_subject_name (name)
) ENGINE=InnoDB;

-- ---------- Students ----------
-- section: 'day' or 'boarding'. admission_no is auto-generated from the
-- enrolling class's admission_prefix unless the admin supplies one.
CREATE TABLE IF NOT EXISTS students (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NULL,
    admission_no    VARCHAR(50)  NOT NULL,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    gender          ENUM('male','female','other') NOT NULL DEFAULT 'male',
    dob             DATE NULL,
    class_id        INT UNSIGNED NULL,
    section         ENUM('day','boarding') NOT NULL DEFAULT 'day',
    -- Form 3 & Form 4 students choose a stream (Science or Arts); Form 1/2 = 'none'.
    stream          ENUM('none','science','arts') NOT NULL DEFAULT 'none',
    guardian_name   VARCHAR(150) NULL,
    guardian_phone  VARCHAR(50)  NULL,
    address         VARCHAR(255) NULL,
    -- Optional passport photo. Stored as a path RELATIVE to /public,
    -- e.g. 'uploads/students/12-1745682739.jpg'. NULL when no photo.
    photo_path      VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_admission_no (admission_no),
    KEY idx_class (class_id),
    CONSTRAINT fk_student_user  FOREIGN KEY (user_id)  REFERENCES users(id)   ON DELETE SET NULL,
    CONSTRAINT fk_student_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Staff ----------
CREATE TABLE IF NOT EXISTS staff (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NULL,
    phone       VARCHAR(50)  NULL,
    position    VARCHAR(100) NULL,
    hire_date   DATE NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_staff_user (user_id),
    CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Attendance ----------
CREATE TABLE IF NOT EXISTS attendance (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id    INT UNSIGNED NOT NULL,
    student_id  INT UNSIGNED NOT NULL,
    date        DATE NOT NULL,
    status      ENUM('present','absent','late') NOT NULL DEFAULT 'present',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_class_student_date (class_id, student_id, date),
    CONSTRAINT fk_att_class   FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
    CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Grades ----------
-- Each row is a single mark for: a student, a subject, an academic year,
-- a term ('Term 1' / 'Term 2' / 'Term 3') and an exam_type ('midterm' / 'endterm').
CREATE TABLE IF NOT EXISTS grades (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id     INT UNSIGNED NOT NULL,
    subject_id     INT UNSIGNED NOT NULL,
    academic_year  VARCHAR(9)   NOT NULL,
    term           VARCHAR(20)  NOT NULL,
    exam_type      ENUM('midterm','endterm') NOT NULL DEFAULT 'endterm',
    score          DECIMAL(5,2) NOT NULL DEFAULT 0,
    recorded_by    INT UNSIGNED NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_student_subject_period (student_id, subject_id, academic_year, term, exam_type),
    KEY idx_grade_lookup (subject_id, academic_year, term, exam_type),
    CONSTRAINT fk_grade_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_grade_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_grade_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Teaching assignments ----------
-- Restricts a teacher (staff) to entering marks for the (class, subject) pairs
-- they are explicitly assigned. Admin manages this table from /teaching.
CREATE TABLE IF NOT EXISTS teaching_assignments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    staff_id    INT UNSIGNED NOT NULL,
    class_id    INT UNSIGNED NOT NULL,
    subject_id  INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_assignment (staff_id, class_id, subject_id),
    KEY idx_assign_staff (staff_id),
    KEY idx_assign_class (class_id),
    CONSTRAINT fk_assign_staff   FOREIGN KEY (staff_id)   REFERENCES staff(id)    ON DELETE CASCADE,
    CONSTRAINT fk_assign_class   FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
    CONSTRAINT fk_assign_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Staff Subjects ----------
-- Subjects each staff member teaches. Captured by the admin on the
-- staff create/edit form. HODs use this to see who teaches what
-- under their department.
CREATE TABLE IF NOT EXISTS staff_subjects (
    staff_id    INT UNSIGNED NOT NULL,
    subject_id  INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (staff_id, subject_id),
    KEY idx_ss_subject (subject_id),
    CONSTRAINT fk_ss_staff   FOREIGN KEY (staff_id)   REFERENCES staff(id)    ON DELETE CASCADE,
    CONSTRAINT fk_ss_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Department Heads ----------
-- A staff member can head one or more subject departments (categories).
-- HODs may grade ANY subject in their category for ANY class without an
-- explicit teaching_assignments row.
CREATE TABLE IF NOT EXISTS department_heads (
    staff_id    INT UNSIGNED NOT NULL,
    category    VARCHAR(20)  NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (staff_id, category),
    KEY idx_dh_category (category),
    CONSTRAINT fk_dh_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Fees ----------
CREATE TABLE IF NOT EXISTS fees (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id  INT UNSIGNED NOT NULL,
    term        VARCHAR(50)  NULL,
    amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid        DECIMAL(12,2) NOT NULL DEFAULT 0,
    note        VARCHAR(255) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fee_student (student_id),
    CONSTRAINT fk_fee_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Fees Management Module ----------
-- Bursar-defined fee per (Form level, section, academic year). Students are
-- auto-assigned the matching amount via student_fees.
CREATE TABLE IF NOT EXISTS fees_structure (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    level         VARCHAR(20)  NOT NULL,
    section       ENUM('day','boarding') NOT NULL,
    academic_year VARCHAR(9)   NOT NULL,
    amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_struct (level, section, academic_year)
) ENGINE=InnoDB;

-- Per-student bill (cached total/paid/status). Created on first sync from
-- fees_structure when the student appears in /bursar/students.
CREATE TABLE IF NOT EXISTS student_fees (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id    INT UNSIGNED NOT NULL,
    academic_year VARCHAR(9)   NOT NULL,
    term          ENUM('Term 1','Term 2','Term 3') NOT NULL DEFAULT 'Term 1',
    total_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_amount   DECIMAL(12,2) NOT NULL DEFAULT 0,
    status        ENUM('not_paid','partial','paid') NOT NULL DEFAULT 'not_paid',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_student_year_term (student_id, academic_year, term),
    KEY idx_status (status),
    KEY idx_period (academic_year, term),
    CONSTRAINT fk_sf_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Transaction history. recorded_by is the bursar who took the payment.
CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_fee_id  INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    payment_date    DATE NOT NULL,
    receipt_no      VARCHAR(50) NOT NULL,
    recorded_by     INT UNSIGNED NULL,
    notes           VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_receipt (receipt_no),
    KEY idx_pay_student (student_id),
    KEY idx_pay_date (payment_date),
    CONSTRAINT fk_pay_sf      FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE,
    CONSTRAINT fk_pay_student FOREIGN KEY (student_id)     REFERENCES students(id)     ON DELETE CASCADE,
    CONSTRAINT fk_pay_user    FOREIGN KEY (recorded_by)    REFERENCES users(id)        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Announcements ----------
CREATE TABLE IF NOT EXISTS announcements (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    title       VARCHAR(200) NOT NULL,
    body        TEXT NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ann_user (user_id),
    CONSTRAINT fk_ann_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Settings (key/value, used by admin customization page) ----------
CREATE TABLE IF NOT EXISTS settings (
    `key`       VARCHAR(100) NOT NULL,
    `value`     TEXT NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB;

-- ---------- Term results (computed when marks are saved; Mid ×/30 + End ×/70) ----------
CREATE TABLE IF NOT EXISTS term_subject_results (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id     INT UNSIGNED NOT NULL,
    class_id       INT UNSIGNED NOT NULL,
    subject_id     INT UNSIGNED NOT NULL,
    academic_year  VARCHAR(9)   NOT NULL,
    term           VARCHAR(20)  NOT NULL,
    mid_marks      DECIMAL(5,2) NULL,
    end_marks      DECIMAL(5,2) NULL,
    total_marks    DECIMAL(5,2) NULL,
    letter_grade   VARCHAR(10)  NULL,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_term_subject_student (student_id, subject_id, academic_year, term),
    KEY idx_term_class_period (class_id, academic_year, term),
    CONSTRAINT fk_tsr_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_tsr_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_tsr_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS term_student_results (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id           INT UNSIGNED NOT NULL,
    class_id             INT UNSIGNED NOT NULL,
    academic_year        VARCHAR(9)   NOT NULL,
    term                 VARCHAR(20)  NOT NULL,
    subjects_with_totals INT UNSIGNED NOT NULL DEFAULT 0,
    average_percentage   DECIMAL(6,2) NULL,
    class_position       INT UNSIGNED NULL,
    rank_cohort          VARCHAR(20) NOT NULL DEFAULT 'class',
    updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_term_student (student_id, academic_year, term),
    KEY idx_tst_class_period (class_id, academic_year, term),
    CONSTRAINT fk_tsi_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_tsi_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Seed data
-- The default admin user is created by running database/install.php
-- (browser: http://localhost/SSDACMIS/public/install.php).
-- That script hashes the password correctly using PHP's password_hash().
-- ============================================================

INSERT IGNORE INTO classes (id, name, level) VALUES
(1, 'Form 1A', 'Form 1'),
(2, 'Form 2A', 'Form 2'),
(3, 'Form 3A', 'Form 3'),
(4, 'Form 4A', 'Form 4');

-- Full curriculum (compulsory + optional). category drives report grouping.
INSERT IGNORE INTO subjects (name, code, category) VALUES
  ('English Language',          'ENG',   'core'),
  ('Mathematics',                'MATH',  'core'),
  ('Citizenship',                'CITZ',  'core'),
  ('Religious Education',        'RE',    'core'),
  ('Biology',                    'BIO',   'science'),
  ('Chemistry',                  'CHEM',  'science'),
  ('Physics',                    'PHY',   'science'),
  ('Geography',                  'GEO',   'arts'),
  ('History',                    'HIST',  'arts'),
  ('Commerce',                   'COM',   'arts'),
  ('Arabic',                     'ARAB',  'optional'),
  ('French',                     'FREN',  'optional'),
  ('Agriculture',                'AGRI',  'optional'),
  ('ICT',                        'ICT',   'optional'),
  ('Accounting',                 'ACC',   'optional'),
  ('Additional Mathematics',     'AMATH', 'optional'),
  ('Literature in English',      'LIT',   'optional'),
  ('Fine Art',                   'ART',   'optional');

INSERT IGNORE INTO announcements (id, user_id, title, body) VALUES
(1, 1, 'Welcome to the new term!', 'Classes resume on Monday. Please check the notice board for the updated timetable.');
