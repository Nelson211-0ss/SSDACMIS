<?php
/**
 * One-shot, idempotent migration to bring an existing SSD-ACMIS database
 * (historically named `schoolreg`) up to the schema needed for teacher
 * mark entry & report cards.
 *
 * Run:
 *   php database/migrate.php
 * or in a browser:
 *   http://localhost/SSDACMIS/database/migrate.php
 *
 * Re-running is safe; each step checks the current state before changing it.
 */

/* Load just the autoloader + config, no session (this script is CLI-friendly). */
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $path = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_readable($path)) require $path;
});

use App\Core\App;
$reflection = new ReflectionClass(App::class);
$cfgProp = $reflection->getProperty('config');
$cfgProp->setAccessible(true);
$cfgProp->setValue(null, require __DIR__ . '/../config/config.php');

use App\Core\Database;

$pdo = Database::connection();
$db  = $pdo->query('SELECT DATABASE()')->fetchColumn();
$out = [];

/** Helpers **/
$columnExists = function (string $table, string $column) use ($pdo, $db): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $stmt->execute([$db, $table, $column]);
    return (bool) $stmt->fetchColumn();
};
$indexExists = function (string $table, string $index) use ($pdo, $db): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
    );
    $stmt->execute([$db, $table, $index]);
    return (bool) $stmt->fetchColumn();
};
$tableExists = function (string $table) use ($pdo, $db): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1'
    );
    $stmt->execute([$db, $table]);
    return (bool) $stmt->fetchColumn();
};
$run = function (string $sql, string $note) use ($pdo, &$out): void {
    $pdo->exec($sql);
    $out[] = "  ok  $note";
};

$out[] = "Migrating database: $db";

/* -- users.role: shared HOD portal account ('hod') ----------------------- */
$roleCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
if ($roleCol && str_contains((string) ($roleCol['Type'] ?? ''), 'hod') === false) {
    $run(
        "ALTER TABLE users
         MODIFY COLUMN role ENUM('admin','staff','student','hod') NOT NULL DEFAULT 'staff'",
        "users.role: added 'hod' for shared Heads of Department login"
    );
} else {
    $out[] = "  --  users.role already includes 'hod' or column missing";
}

/* -- users.department (informational label for HOD accounts) ------------- */
if (!$columnExists('users', 'department')) {
    $run(
        "ALTER TABLE users ADD COLUMN department VARCHAR(60) NULL AFTER role",
        "users.department column added (label for HOD accounts)"
    );
} else {
    $out[] = "  --  users.department already present";
}

/* -- Seed Form 4 class (Form 1–4 mark upload) ---------------------------- */
$hasF4 = $pdo->query("SELECT 1 FROM classes WHERE name = 'Form 4A' OR level = 'Form 4' LIMIT 1")->fetchColumn();
if (!$hasF4) {
    $run(
        "INSERT INTO classes (name, level) VALUES ('Form 4A', 'Form 4')",
        "class Form 4A (Form 4) added"
    );
} else {
    $out[] = "  --  Form 4 class already present";
}

/* -- Default shared HOD user (one login for all HODs; change password!) --- */
$hodEmail = 'hod@school.local';
$chkHod = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$chkHod->execute([$hodEmail]);
if (!$chkHod->fetch()) {
    $hash = password_hash('hod123', PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'hod', 'active')");
    $ins->execute(['Heads of Department', $hodEmail, $hash]);
    $out[] = "  ok  created shared HOD user: $hodEmail / hod123 (change password in production)";
} else {
    $out[] = "  --  shared HOD user $hodEmail already exists";
}
$n = $pdo->exec(
    "UPDATE users SET role = 'hod', name = 'Heads of Department' WHERE email = " . $pdo->quote($hodEmail) . " AND role != 'hod'"
);
if ($n > 0) {
    $out[] = "  ok  set role to hod for $hodEmail (shared HOD account)";
}

/* -- subjects.category --------------------------------------------------- */
if (!$columnExists('subjects', 'category')) {
    $run(
        "ALTER TABLE subjects ADD COLUMN category VARCHAR(20) NOT NULL DEFAULT 'optional' AFTER code",
        "subjects.category column added"
    );
} else {
    $out[] = "  --  subjects.category already present";
}

/* -- classes.class_teacher_id ------------------------------------------- */
if (!$columnExists('classes', 'class_teacher_id')) {
    $run(
        "ALTER TABLE classes
         ADD COLUMN class_teacher_id INT UNSIGNED NULL AFTER level,
         ADD KEY idx_class_teacher (class_teacher_id)",
        "classes.class_teacher_id column added"
    );
} else {
    $out[] = "  --  classes.class_teacher_id already present";
}

/* -- grades.academic_year + exam_type + recorded_by --------------------- */
$currentAY = (date('n') >= 9)
    ? date('Y') . '/' . (date('Y') + 1)
    : (date('Y') - 1) . '/' . date('Y');

if (!$columnExists('grades', 'academic_year')) {
    $run(
        "ALTER TABLE grades ADD COLUMN academic_year VARCHAR(9) NOT NULL DEFAULT "
        . $pdo->quote($currentAY) . " AFTER subject_id",
        "grades.academic_year column added (default $currentAY)"
    );
} else {
    $out[] = "  --  grades.academic_year already present";
}

if (!$columnExists('grades', 'exam_type')) {
    $run(
        "ALTER TABLE grades ADD COLUMN exam_type ENUM('midterm','endterm') NOT NULL DEFAULT 'endterm' AFTER term",
        "grades.exam_type column added"
    );
} else {
    $out[] = "  --  grades.exam_type already present";
}

if (!$columnExists('grades', 'recorded_by')) {
    $run(
        "ALTER TABLE grades ADD COLUMN recorded_by INT UNSIGNED NULL AFTER score,
         ADD CONSTRAINT fk_grade_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL",
        "grades.recorded_by column added"
    );
} else {
    $out[] = "  --  grades.recorded_by already present";
}

/* -- swap unique key on grades to include year + exam_type --------------
 * Order matters: the old uniq_student_subject_term is currently the index
 * MySQL is using to back the student_id FK. We must first ADD the new
 * unique key (also leading on student_id) so the FK has a valid backing
 * index, *then* drop the old key. */
if (!$indexExists('grades', 'uniq_student_subject_period')) {
    $run(
        "ALTER TABLE grades ADD UNIQUE KEY uniq_student_subject_period
         (student_id, subject_id, academic_year, term, exam_type)",
        "new grades unique key (student, subject, year, term, exam_type) added"
    );
} else {
    $out[] = "  --  grades new unique key already present";
}
if ($indexExists('grades', 'uniq_student_subject_term')) {
    $run("ALTER TABLE grades DROP INDEX uniq_student_subject_term", "old grades unique key dropped");
}
if (!$indexExists('grades', 'idx_grade_lookup')) {
    $run(
        "ALTER TABLE grades ADD KEY idx_grade_lookup (subject_id, academic_year, term, exam_type)",
        "grades lookup index added"
    );
}

/* -- teaching_assignments ----------------------------------------------- */
if (!$tableExists('teaching_assignments')) {
    $run("
        CREATE TABLE teaching_assignments (
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
        ) ENGINE=InnoDB
    ", "teaching_assignments table created");
} else {
    $out[] = "  --  teaching_assignments already exists";
}

/* -- back-fill subject categories for legacy seed names ----------------- */
$catMap = [
    'Mathematics' => 'core', 'English Language' => 'core', 'English' => 'core',
    'Citizenship' => 'core', 'Religious Education' => 'core',
    'Biology' => 'science', 'Chemistry' => 'science', 'Physics' => 'science',
    'Geography' => 'arts',  'History' => 'arts',     'Commerce' => 'arts',
    'Arabic' => 'optional', 'French' => 'optional',  'Agriculture' => 'optional',
    'ICT' => 'optional',    'Accounting' => 'optional',
    'Additional Mathematics' => 'optional', 'Literature in English' => 'optional',
    'Fine Art' => 'optional',
];
$upd = $pdo->prepare("UPDATE subjects SET category = ? WHERE name = ? AND (category = '' OR category = 'optional')");
$updCount = 0;
foreach ($catMap as $name => $cat) {
    $upd->execute([$cat, $name]);
    if ($upd->rowCount() > 0) $updCount += $upd->rowCount();
}
$out[] = "  ok  back-filled category on $updCount existing subject row(s)";

/* -- seed full curriculum (only inserts what's missing) ----------------- */
$seed = [
    ['English Language',         'ENG',   'core'],
    ['Mathematics',              'MATH',  'core'],
    ['Citizenship',              'CITZ',  'core'],
    ['Religious Education',      'RE',    'core'],
    ['Biology',                  'BIO',   'science'],
    ['Chemistry',                'CHEM',  'science'],
    ['Physics',                  'PHY',   'science'],
    ['Geography',                'GEO',   'arts'],
    ['History',                  'HIST',  'arts'],
    ['Commerce',                 'COM',   'arts'],
    ['Arabic',                   'ARAB',  'optional'],
    ['French',                   'FREN',  'optional'],
    ['Agriculture',              'AGRI',  'optional'],
    ['ICT',                      'ICT',   'optional'],
    ['Accounting',               'ACC',   'optional'],
    ['Additional Mathematics',   'AMATH', 'optional'],
    ['Literature in English',    'LIT',   'optional'],
    ['Fine Art',                 'ART',   'optional'],
];
$ins = $pdo->prepare("INSERT IGNORE INTO subjects (name, code, category) VALUES (?, ?, ?)");
$insCount = 0;
foreach ($seed as [$n, $c, $cat]) {
    $ins->execute([$n, $c, $cat]);
    if ($ins->rowCount() > 0) $insCount++;
}
$out[] = "  ok  seeded $insCount new subject row(s)";

/* -- classes.admission_prefix ------------------------------------------ */
if (!$columnExists('classes', 'admission_prefix')) {
    $run(
        "ALTER TABLE classes ADD COLUMN admission_prefix VARCHAR(10) NOT NULL DEFAULT '' AFTER level",
        "classes.admission_prefix column added"
    );
} else {
    $out[] = "  --  classes.admission_prefix already present";
}

/* Back-fill empty prefixes from class name (e.g. 'Form 1A' -> 'F1A'). */
$prefixDerive = static function (string $name): string {
    preg_match_all('/([A-Z])|(\d+)/', $name, $m, PREG_SET_ORDER);
    $parts = [];
    foreach ($m as $tok) $parts[] = $tok[0];
    $prefix = strtoupper(implode('', $parts));
    if ($prefix === '') {
        $prefix = strtoupper(preg_replace('/[^a-z0-9]/i', '', $name));
    }
    return substr($prefix, 0, 10);
};
$rows = $pdo->query("SELECT id, name FROM classes WHERE admission_prefix = '' OR admission_prefix IS NULL")->fetchAll();
$prefUpd = $pdo->prepare("UPDATE classes SET admission_prefix = ? WHERE id = ?");
$pCount = 0;
foreach ($rows as $r) {
    $prefUpd->execute([$prefixDerive((string) $r['name']), (int) $r['id']]);
    $pCount++;
}
$out[] = "  ok  back-filled admission_prefix on $pCount class row(s)";

/* -- students.section -------------------------------------------------- */
if (!$columnExists('students', 'section')) {
    $run(
        "ALTER TABLE students ADD COLUMN section ENUM('day','boarding') NOT NULL DEFAULT 'day' AFTER class_id",
        "students.section column added"
    );
} else {
    $out[] = "  --  students.section already present";
}

/* -- students.stream (science/arts for Form 3 & 4) --------------------- */
if (!$columnExists('students', 'stream')) {
    $run(
        "ALTER TABLE students ADD COLUMN stream ENUM('none','science','arts') NOT NULL DEFAULT 'none' AFTER section",
        "students.stream column added (Form 3/4 Science vs Arts)"
    );
} else {
    // Normalize older variants: an earlier draft used 'sciences' (plural).
    // Convert to the canonical 'science' (singular, matching subjects.category)
    // so report ranking and the stream filter agree.
    $streamCol = $pdo->query("SHOW COLUMNS FROM students LIKE 'stream'")->fetch(PDO::FETCH_ASSOC);
    $streamType = (string) ($streamCol['Type'] ?? '');
    if (str_contains($streamType, 'sciences')) {
        $run(
            "ALTER TABLE students MODIFY COLUMN stream ENUM('none','science','arts','sciences') NOT NULL DEFAULT 'none'",
            "students.stream: temporarily allowed both 'science' and 'sciences'"
        );
        $upd = $pdo->exec("UPDATE students SET stream = 'science' WHERE stream = 'sciences'");
        $out[] = "  ok  students.stream: rewrote $upd row(s) from 'sciences' to 'science'";
        $run(
            "ALTER TABLE students MODIFY COLUMN stream ENUM('none','science','arts') NOT NULL DEFAULT 'none'",
            "students.stream: collapsed enum to ('none','science','arts')"
        );
    } else {
        $out[] = "  --  students.stream already present";
    }
}

/* -- students.photo_path: optional passport photo --------------------- */
if (!$columnExists('students', 'photo_path')) {
    $run(
        "ALTER TABLE students ADD COLUMN photo_path VARCHAR(255) NULL AFTER address",
        "students.photo_path column added (optional passport photo)"
    );
} else {
    $out[] = "  --  students.photo_path already present";
}

/* -- staff_subjects ---------------------------------------------------- */
if (!$tableExists('staff_subjects')) {
    $run("
        CREATE TABLE staff_subjects (
            staff_id    INT UNSIGNED NOT NULL,
            subject_id  INT UNSIGNED NOT NULL,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (staff_id, subject_id),
            KEY idx_ss_subject (subject_id),
            CONSTRAINT fk_ss_staff   FOREIGN KEY (staff_id)   REFERENCES staff(id)    ON DELETE CASCADE,
            CONSTRAINT fk_ss_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ", "staff_subjects table created");
} else {
    $out[] = "  --  staff_subjects already exists";
}

/* -- department_heads -------------------------------------------------- */
if (!$tableExists('department_heads')) {
    $run("
        CREATE TABLE department_heads (
            staff_id    INT UNSIGNED NOT NULL,
            category    VARCHAR(20)  NOT NULL,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (staff_id, category),
            KEY idx_dh_category (category),
            CONSTRAINT fk_dh_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ", "department_heads table created");
} else {
    $out[] = "  --  department_heads already exists";
}

/* -- subjects.is_offered (school admin curates the curriculum) -------- */
if (!$columnExists('subjects', 'is_offered')) {
    $run(
        "ALTER TABLE subjects ADD COLUMN is_offered TINYINT(1) NOT NULL DEFAULT 1 AFTER category",
        "subjects.is_offered column added (admin-curated curriculum)"
    );
    // Make sure existing subjects keep behaving like before (all offered).
    $pdo->exec("UPDATE subjects SET is_offered = 1");
    $out[] = "  ok  subjects.is_offered: defaulted existing rows to offered=1";
} else {
    $out[] = "  --  subjects.is_offered already present";
}

/* -- term_subject_results / term_student_results (South Sudan totals + ranking) */
if (!$tableExists('term_subject_results')) {
    $run("
        CREATE TABLE term_subject_results (
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
        ) ENGINE=InnoDB
    ", 'term_subject_results table created');
} else {
    $out[] = '  --  term_subject_results already exists';
}

if (!$tableExists('term_student_results')) {
    $run("
        CREATE TABLE term_student_results (
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
        ) ENGINE=InnoDB
    ", 'term_student_results table created');
} else {
    $out[] = '  --  term_student_results already exists';
}

/* ============================================================
 * Bursar / Fees Management Module
 * ============================================================
 * - users.role ENUM gains 'bursar' (separate from admin/staff/student/hod)
 * - fees_structure: bursar-defined fee per (level, section, academic_year)
 * - student_fees:   per-student auto-assigned bill (cached total/paid/status)
 * - payments:       transaction history (receipt no., recorded by bursar)
 *
 * Re-running this script is safe; each step checks the current state.
 * ============================================================ */

/* -- users.role: add 'bursar' ------------------------------------------ */
$roleCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
if ($roleCol && str_contains((string) ($roleCol['Type'] ?? ''), 'bursar') === false) {
    $run(
        "ALTER TABLE users
         MODIFY COLUMN role ENUM('admin','staff','student','hod','bursar') NOT NULL DEFAULT 'staff'",
        "users.role: added 'bursar' (Fees Management portal)"
    );
} else {
    $out[] = "  --  users.role already includes 'bursar'";
}

/* -- fees_structure ---------------------------------------------------- */
if (!$tableExists('fees_structure')) {
    $run("
        CREATE TABLE fees_structure (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            level         VARCHAR(20)  NOT NULL,
            section       ENUM('day','boarding') NOT NULL,
            academic_year VARCHAR(9)   NOT NULL,
            amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_struct (level, section, academic_year)
        ) ENGINE=InnoDB
    ", "fees_structure table created");
} else {
    $out[] = "  --  fees_structure already exists";
}

/* -- student_fees ------------------------------------------------------ */
if (!$tableExists('student_fees')) {
    $run("
        CREATE TABLE student_fees (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id    INT UNSIGNED NOT NULL,
            academic_year VARCHAR(9)   NOT NULL,
            total_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
            paid_amount   DECIMAL(12,2) NOT NULL DEFAULT 0,
            status        ENUM('not_paid','partial','paid') NOT NULL DEFAULT 'not_paid',
            created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_student_year (student_id, academic_year),
            KEY idx_status (status),
            CONSTRAINT fk_sf_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ", "student_fees table created");
} else {
    $out[] = "  --  student_fees already exists";
}

/* -- payments (transaction history) ----------------------------------- */
if (!$tableExists('payments')) {
    $run("
        CREATE TABLE payments (
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
        ) ENGINE=InnoDB
    ", "payments table created");
} else {
    $out[] = "  --  payments already exists";
}

/* -- student_fees: split bills per term -------------------------------- *
 * Originally each student had one bill per academic year. We now bill
 * per (year + term) so the bursar can record payments against a specific
 * term and run per-term reports.
 *
 *   1. Add the `term` column (existing rows default to 'Term 1').
 *   2. Replace UNIQUE(student_id, academic_year) with the term-aware
 *      UNIQUE(student_id, academic_year, term).
 *   3. Add a lookup index on (academic_year, term).
 *
 * Term 2 and Term 3 rows are auto-created on first sync by FeesService.
 */
if ($tableExists('student_fees')) {
    if (!$columnExists('student_fees', 'term')) {
        $run(
            "ALTER TABLE student_fees
             ADD COLUMN term ENUM('Term 1','Term 2','Term 3') NOT NULL DEFAULT 'Term 1' AFTER academic_year",
            "student_fees.term column added (existing rows -> Term 1)"
        );
    } else {
        $out[] = "  --  student_fees.term already present";
    }

    if (!$indexExists('student_fees', 'uniq_student_year_term')) {
        $run(
            "ALTER TABLE student_fees ADD UNIQUE KEY uniq_student_year_term (student_id, academic_year, term)",
            "student_fees: added unique key (student, year, term)"
        );
    } else {
        $out[] = "  --  student_fees uniq_student_year_term already present";
    }

    if ($indexExists('student_fees', 'uniq_student_year')) {
        $run(
            "ALTER TABLE student_fees DROP INDEX uniq_student_year",
            "student_fees: dropped legacy unique key (student, year)"
        );
    }

    if (!$indexExists('student_fees', 'idx_period')) {
        $run(
            "ALTER TABLE student_fees ADD KEY idx_period (academic_year, term)",
            "student_fees: added (year, term) lookup index"
        );
    }
}

/* -- Default bursar account (change password in production!) ---------- */
$bursarEmail = 'bursar@school.local';
$chkBur = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$chkBur->execute([$bursarEmail]);
if (!$chkBur->fetch()) {
    $hash = password_hash('bursar123', PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'bursar', 'active')");
    $ins->execute(['School Bursar', $bursarEmail, $hash]);
    $out[] = "  ok  created default bursar: $bursarEmail / bursar123 (change password in production)";
} else {
    $out[] = "  --  bursar user $bursarEmail already exists";
}

$out[] = "Done.";

/* Output -------------------------------------------------------------- */
$cli = (PHP_SAPI === 'cli');
if (!$cli) header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $out) . "\n";
