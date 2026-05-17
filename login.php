<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

start_session_once();

if (current_user()) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pin = $_POST['pin'] ?? '';
    $user = login_by_pin($pin);
    if ($user) {
        redirect('index.php');
    }
    $now = time();
    $lockUntil = $_SESSION['_pin_lock_until'] ?? 0;
    if ($lockUntil > $now) {
        $error = 'Too many wrong PINs. Try again in ' . ($lockUntil - $now) . 's.';
    } else {
        $error = 'PIN not recognised.';
    }
}

$pageTitle = 'Sign in — LG Task Manager';
include __DIR__ . '/includes/header.php';
?>
<div class="login-card">
    <h1>Enter your PIN</h1>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input
            type="password"
            inputmode="numeric"
            pattern="[0-9]{4,6}"
            maxlength="6"
            name="pin"
            placeholder="4-6 digit PIN"
            autofocus
            required
            class="pin-input"
        >
        <button type="submit" class="btn btn-primary">Sign in</button>
    </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
