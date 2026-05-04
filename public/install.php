<?php
/**
 * One-shot installer.
 *
 * 1) Verifies DB connectivity using your config / .env.
 * 2) Imports database/schema.sql if tables are missing.
 * 3) Creates a default admin user with a properly hashed password.
 *
 * After a successful install, DELETE this file from production!
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Database;

header('Content-Type: text/html; charset=utf-8');

$defaults = [
    'email'    => 'admin@school.local',
    'password' => 'admin123',
    'name'     => 'System Admin',
];

$messages = [];
$ok = true;

try {
    $pdo = Database::connection();
    $messages[] = ['ok', 'Database connection OK (' . App::config('db.host') . '/' . App::config('db.database') . ').'];
} catch (Throwable $e) {
    $ok = false;
    $messages[] = ['err', 'DB connection failed: ' . $e->getMessage()];
}

if ($ok) {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('users', $tables, true)) {
        $sql = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
        try {
            $pdo->exec($sql);
            $messages[] = ['ok', 'Schema imported.'];
        } catch (Throwable $e) {
            $ok = false;
            $messages[] = ['err', 'Schema import failed: ' . $e->getMessage()];
        }
    } else {
        $messages[] = ['ok', 'Schema already present.'];
    }
}

if ($ok) {
    $exists = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $exists->execute([$defaults['email']]);
    if ($exists->fetch()) {
        $messages[] = ['ok', 'Default admin already exists (' . $defaults['email'] . ').'];
    } else {
        $hash = password_hash($defaults['password'], PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')")
            ->execute([$defaults['name'], $defaults['email'], $hash]);
        $messages[] = ['ok', 'Admin user created: ' . $defaults['email'] . ' / ' . $defaults['password']];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Installer · School Management System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 720px;">
  <h3 class="mb-4">Installer</h3>
  <ul class="list-group mb-4">
    <?php foreach ($messages as [$type, $msg]): ?>
      <li class="list-group-item d-flex justify-content-between">
        <span><?= htmlspecialchars($msg) ?></span>
        <span class="badge bg-<?= $type === 'ok' ? 'success' : 'danger' ?>"><?= $type === 'ok' ? 'OK' : 'ERROR' ?></span>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($ok): ?>
    <div class="alert alert-warning">
      <strong>Important:</strong> delete <code>public/install.php</code> from your server now.
      Then change the default admin password from the Staff page after logging in.
    </div>
    <a class="btn btn-primary" href="login">Go to login</a>
  <?php endif; ?>
</div>
</body>
</html>
