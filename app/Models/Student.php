<?php
namespace App\Models;

use App\Core\Database;

class Student
{
    /**
     * Hard cap on the rows returned to any list/AJAX endpoint. Bound page
     * weight, query time, and memory regardless of how the school grows.
     * Beyond this cap users are nudged to refine their search.
     */
    public const LIST_LIMIT = 500;

    public static function all(string $search = '', int $limit = self::LIST_LIMIT): array
    {
        $limit = max(1, min(2000, $limit));
        if ($search !== '') {
            $like = '%' . $search . '%';
            return Database::query(
                "SELECT s.*, c.name AS class_name
                 FROM students s LEFT JOIN classes c ON c.id = s.class_id
                 WHERE s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ?
                    OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?
                 ORDER BY s.created_at DESC
                 LIMIT $limit",
                [$like, $like, $like, $like]
            )->fetchAll();
        }
        return Database::query(
            "SELECT s.*, c.name AS class_name
             FROM students s LEFT JOIN classes c ON c.id = s.class_id
             ORDER BY s.created_at DESC
             LIMIT $limit"
        )->fetchAll();
    }

    /**
     * Total row count matching the search — used by the list view to decide
     * whether the cap was hit and a "narrow your search" banner is needed.
     */
    public static function countAll(string $search = ''): int
    {
        if ($search !== '') {
            $like = '%' . $search . '%';
            $row = Database::query(
                "SELECT COUNT(*) AS n FROM students s
                 WHERE s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ?
                    OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?",
                [$like, $like, $like, $like]
            )->fetch();
        } else {
            $row = Database::query("SELECT COUNT(*) AS n FROM students")->fetch();
        }
        return (int) ($row['n'] ?? 0);
    }

    public static function find(int $id): ?array
    {
        $row = Database::query("SELECT * FROM students WHERE id = ?", [$id])->fetch();
        return $row ?: null;
    }

    public static function create(array $d): int
    {
        Database::query(
            "INSERT INTO students (admission_no, first_name, last_name, gender, dob, class_id, section, stream,
                                   guardian_name, guardian_phone, address, photo_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $d['admission_no'], $d['first_name'], $d['last_name'], $d['gender'], $d['dob'] ?: null,
                $d['class_id'] ?: null, $d['section'], $d['stream'] ?? 'none',
                $d['guardian_name'], $d['guardian_phone'], $d['address'],
                $d['photo_path'] ?? null,
            ]
        );
        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Update everything EXCEPT photo_path. Photo writes go through
     * Student::setPhoto() / clearPhoto() so the controller's photo handling
     * stays orthogonal to the rest of the form fields.
     */
    public static function update(int $id, array $d): void
    {
        Database::query(
            "UPDATE students SET admission_no=?, first_name=?, last_name=?, gender=?, dob=?, class_id=?,
                                 section=?, stream=?, guardian_name=?, guardian_phone=?, address=? WHERE id=?",
            [
                $d['admission_no'], $d['first_name'], $d['last_name'], $d['gender'], $d['dob'] ?: null,
                $d['class_id'] ?: null, $d['section'], $d['stream'] ?? 'none',
                $d['guardian_name'], $d['guardian_phone'], $d['address'], $id,
            ]
        );
    }

    public static function setPhoto(int $id, string $relativePath): void
    {
        Database::query(
            "UPDATE students SET photo_path = ? WHERE id = ?",
            [$relativePath, $id]
        );
    }

    public static function clearPhoto(int $id): void
    {
        Database::query("UPDATE students SET photo_path = NULL WHERE id = ?", [$id]);
    }

    public static function delete(int $id): void
    {
        Database::query("DELETE FROM students WHERE id = ?", [$id]);
    }

    /**
     * Delete every learner record and downstream rows (grades, attendance,
     * fees, term results CASCADE in schema). Removes all user accounts with
     * role student. Returns passport paths for the caller to unlink on disk.
     *
     * @return array{student_rows: int, user_rows_deleted: int, photo_paths: list<string>}
     */
    public static function purgeAll(): array
    {
        $pdo = Database::connection();
        $photoStmt = $pdo->query(
            "SELECT photo_path FROM students WHERE photo_path IS NOT NULL AND TRIM(photo_path) <> ''"
        );
        $photos = array_values(array_unique(array_filter(
            array_map('strval', $photoStmt ? $photoStmt->fetchAll(\PDO::FETCH_COLUMN) : [])
        )));

        $countStudents = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
        $countUsersRow = Database::query(
            "SELECT COUNT(*) AS n FROM users WHERE role = 'student'"
        )->fetch();

        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM students');
            Database::query("DELETE FROM users WHERE role = 'student'");
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [
            'student_rows'       => $countStudents,
            'user_rows_deleted' => (int) ($countUsersRow['n'] ?? 0),
            'photo_paths'      => $photos,
        ];
    }

    /**
     * Generate the next admission number for the given class.
     * Combines the class's admission_prefix with a zero-padded sequence
     * derived from the highest existing number that already uses that prefix.
     * Returns null if the class has no admission_prefix set.
     */
    public static function nextAdmissionNo(int $classId): ?string
    {
        $row = Database::query(
            "SELECT admission_prefix FROM classes WHERE id = ?",
            [$classId]
        )->fetch();
        if (!$row || $row['admission_prefix'] === '') return null;
        $prefix = (string) $row['admission_prefix'];

        $like = $prefix . '%';
        $max = Database::query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(admission_no, ?) AS UNSIGNED)), 0) AS n
             FROM students
             WHERE class_id = ? AND admission_no LIKE ?",
            [strlen($prefix) + 1, $classId, $like]
        )->fetch();
        $next = ((int) ($max['n'] ?? 0)) + 1;
        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
