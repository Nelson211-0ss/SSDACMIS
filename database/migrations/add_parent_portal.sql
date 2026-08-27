-- ============================================================
-- Migration: Parent Portal
-- Run this ONCE on an existing ssdacmis installation:
--   mysql -u root -p ssdacmis < database/migrations/add_parent_portal.sql
--
-- Fresh installs: import database/schema.sql instead (already includes
-- all these changes). Do NOT run this on a fresh install.
-- ============================================================

USE ssdacmis;

-- 1. Allow users.role = 'parent'
ALTER TABLE users MODIFY COLUMN role
    ENUM('admin','staff','student','hod','bursar','school_admin','parent') NOT NULL DEFAULT 'staff';

-- 2. Parent -> student links (a parent may have more than one child in the
--    school; admin/school_admin manage these from /parents). is_primary
--    marks the one linked child whose admission number is this parent's
--    sign-in credential (see Auth::attemptParent()).
CREATE TABLE IF NOT EXISTS parent_students (
    school_id      INT UNSIGNED NOT NULL DEFAULT 1,
    parent_user_id INT UNSIGNED NOT NULL,
    student_id     INT UNSIGNED NOT NULL,
    is_primary     TINYINT(1) NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (parent_user_id, student_id),
    KEY idx_ps_student (student_id),
    KEY idx_ps_school (school_id),
    CONSTRAINT fk_ps_parent  FOREIGN KEY (parent_user_id) REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_ps_student FOREIGN KEY (student_id)     REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_ps_school  FOREIGN KEY (school_id)      REFERENCES schools(id)  ON DELETE CASCADE
) ENGINE=InnoDB;
