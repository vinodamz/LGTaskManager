<?php
/**
 * First-run admin bootstrap. Open this once in your browser after creating
 * the database and running sql/schema.sql.
 *
 * DELETE THIS FILE after the admin user exists.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$adminExists = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")
    ->fetchColumn() > 0;

if ($adminExists) {
    http_response_code(403);
    echo '<!doctype html><meta charset=utf-8><title>Already installed</title>';
    echo '<h1>Already installed</h1>';
    echo '<p>An admin user already exists. Delete <code>install.php</code> from the server now.</p>';
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $pin  = preg_replace('/\D/', '', $_POST['pin'] ?? '');
    if ($name === '' || strlen($pin) < 4 || strlen($pin) > 6) {
        $error = 'Name and a 4–6 digit PIN are required.';
    } else {
        $stmt = db()->prepare("INSERT INTO users (name, pin_hash, role) VALUES (:n, :h, 'admin')");
        $stmt->execute([':n' => $name, ':h' => password_hash($pin, PASSWORD_DEFAULT)]);
        echo '<!doctype html><meta charset=utf-8><title>Installed</title>';
        echo '<h1>Admin created ✔</h1>';
        echo '<p>Now <strong>delete <code>install.php</code></strong> from the server, then ';
        echo '<a href="login.php">log in</a>.</p>';
        exit;
    }
}
?>
<!doctype html>
<meta charset=utf-8>
<title>Install LG Task Manager</title>
<link rel="stylesheet" href="assets/css/style.css">
<main class="container">
    <h1>Create the first admin</h1>
    <p>This page is only available because no admin exists yet.</p>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="login-card">
        <label>Your name <input name="name" required maxlength="100"></label>
        <label>PIN (4–6 digits)
            <input name="pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" required>
        </label>
        <button class="btn btn-primary">Create admin</button>
    </form>
</main>
