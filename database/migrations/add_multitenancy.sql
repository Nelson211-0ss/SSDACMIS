-- ============================================================
-- Migration: Multi-Tenancy Support
-- Run this ONCE on an existing ssdacmis installation:
--   mysql -u root -p ssdacmis < database/migrations/add_multitenancy.sql
--
-- Fresh installs: import database/schema.sql instead (already includes
-- all these changes). Do NOT run this on a fresh install.
-- ============================================================

USE ssdacmis;

-- -------------------------------------------------------
-- 1. Schools table (master tenant registry)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS schools (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(150) NOT NULL,
    code       VARCHAR(20)  NOT NULL,
    email      VARCHAR(190) NULL,
    phone      VARCHAR(30)  NULL,
    address    TEXT         NULL,
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_school_code (code)
) ENGINE=InnoDB;

-- Seed the default school so all existing data gets school_id = 1
INSERT IGNORE INTO schools (id, name, code) VALUES (1, 'Default School', 'DEFAULT');

-- -------------------------------------------------------
-- 2. Password resets (forgot-password flow)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(190) NOT NULL,
    token      VARCHAR(64)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_token (token),
    KEY idx_pr_email (email)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- 3. users — add school_id + school_admin role
-- -------------------------------------------------------
ALTER TABLE users
    ADD COLUMN school_id INT UNSIGNED NULL DEFAULT NULL AFTER id;

ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','staff','student','hod','bursar','school_admin') NOT NULL DEFAULT 'staff';

-- Existing non-admin users belong to school 1; admin = NULL (global)
UPDATE users SET school_id = 1 WHERE role != 'admin';

ALTER TABLE users
    ADD KEY idx_users_school (school_id),
    ADD CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL;

-- -------------------------------------------------------
-- 4. students — add school_id, update admission_no unique
-- -------------------------------------------------------
ALTER TABLE students
    ADD COLUMN school_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

UPDATE students SET school_id = 1;

ALTER TABLE students
    DROP INDEX uniq_admission_no,
    ADD UNIQUE KEY uniq_admission_school (school_id, admission_no),
    ADD KEY idx_students_school (school_id),
    ADD CONSTRAINT fk_students_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

-- -------------------------------------------------------
-- 5. staff — add school_id
-- -------------------------------------------------------
ALTER TABLE staff
    ADD COLUMN school_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

UPDATE staff SET school_id = 1;

ALTER TABLE staff
    ADD KEY idx_staff_school (school_id),
    ADD CONSTRAINT fk_staff_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

-- -------------------------------------------------------
-- 6. classes — add school_id, update unique name constraint
-- -------------------------------------------------------
ALTER TABLE classes
    ADD COLUMN school_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

UPDATE classes SET school_id = 1;

ALTER TABLE classes
    DROP INDEX uniq_class_name,
    ADD UNIQUE KEY uniq_class_school_name (school_id, name),
    ADD KEY idx_classes_school (school_id),
    ADD CONSTRAINT fk_classes_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

-- -------------------------------------------------------
-- 7. subjects — add school_id, update unique name constraint
-- -------------------------------------------------------
ALTER TABLE subjects
    ADD COLUMN school_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

UPDATE subjects SET school_id = 1;

ALTER TABLE subjects
    DROP INDEX uniq_subject_name,
    ADD UNIQUE KEY uniq_subject_school_name (school_id, name),
    ADD KEY idx_subjects_school (school_id),
    ADD CONSTRAINT fk_subjects_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

-- -------------------------------------------------------
-- 8. attendance — add school_id
-- -------------------------------------------------------
ALTER TABLE attendance
    ADD COLUMN school_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

UPDATE attendance SET school_id = 1;

ALTER TABLE attendance
    ADD KEY idx_attendance_school (school_id),
    ADD CONSTRAINT fk_attendance_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

-- -------------------------------------------------------
-- 9. grades — add school_id
-- -------------------------------------------------------
ALTER TABLE grades
    ADD COLUMN school_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

UPDATE grades SET school_id = 1;

ALTER TABLE grades
    ADD KEY idx_grades_school (school_id),
    ADD CONSTRAINT fk_grades_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

-- -------------------------------------------------------
-- 10. fees_structure — add school_id, update unique constraint
-- -------------------------------------------------------
ALTER TABLE fees_structure
    ADD COLUMN school_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

UPDATE fees_structure SET school_id = 1;

ALTER TABLE fees_structure
    DROP INDEX uniq_struct,
    ADD UNIQUE KEY uniq_fees_struct (school_id, level, section, academic_year),
    ADD KEY idx_fees_struct_school (school_id),
    ADD CONSTRAINT fk_fees_struct_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

-- -------------------------------------------------------
-- 11. student_fees — add school_id
-- -------------------------------------------------------
ALTER TABLE student_fees
    ADD COLUMN school_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

UPDATE student_fees SET school_id = 1;

ALTER TABLE student_fees
    ADD KEY idx_student_fees_school (school_id),
    ADD CONSTRAINT fk_student_fees_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

-- -------------------------------------------------------
-- 12. payments — add school_id
-- -------------------------------------------------------
ALTER TABLE payments
    ADD COLUMN school_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

UPDATE payments SET school_id = 1;

ALTER TABLE payments
    ADD KEY idx_payments_school (school_id),
    ADD CONSTRAINT fk_payments_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

-- -------------------------------------------------------
-- 13. announcements — add school_id
-- -------------------------------------------------------
ALTER TABLE announcements
    ADD COLUMN school_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

UPDATE announcements SET school_id = 1;

ALTER TABLE announcements
    ADD KEY idx_announcements_school (school_id),
    ADD CONSTRAINT fk_announcements_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

-- -------------------------------------------------------
-- 14. teaching_assignments — add school_id
-- -------------------------------------------------------
ALTER TABLE teaching_assignments
    ADD COLUMN school_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

UPDATE teaching_assignments SET school_id = 1;

ALTER TABLE teaching_assignments
    ADD KEY idx_ta_school (school_id),
    ADD CONSTRAINT fk_ta_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

-- -------------------------------------------------------
-- 15. department_heads — add school_id
-- -------------------------------------------------------
ALTER TABLE department_heads
    ADD COLUMN school_id INT UNSIGNED NOT NULL DEFAULT 1;

UPDATE department_heads SET school_id = 1;

ALTER TABLE department_heads
    ADD KEY idx_dh_school (school_id),
    ADD CONSTRAINT fk_dh_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;
