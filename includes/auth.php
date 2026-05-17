<?php
// Session + PIN authentication helpers.

require_once __DIR__ . '/db.php';

function app_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/config.php';
    }
    return $cfg;
}

function start_session_once(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $cfg = app_config();
        session_name($cfg['app']['session_name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        date_default_timezone_set($cfg['app']['timezone'] ?? 'UTC');
    }
}

/**
 * Look up the user matching this PIN.
 * PINs are bcrypt-hashed so we can't query by hash directly —
 * we iterate active users and password_verify. Fine for small staff lists.
 */
function login_by_pin(string $pin): ?array
{
    start_session_once();
    $cfg = app_config();

    // Rate-limit
    $now = time();
    $tries = $_SESSION['_pin_tries'] ?? 0;
    $lockUntil = $_SESSION['_pin_lock_until'] ?? 0;
    if ($lockUntil > $now) {
        return null;
    }

    $pin = preg_replace('/\D/', '', $pin);
    if ($pin === '' || strlen($pin) < 4 || strlen($pin) > 6) {
        $_SESSION['_pin_tries'] = $tries + 1;
        return null;
    }

    $stmt = db()->query("SELECT id, name, pin_hash, role FROM users WHERE active = 1");
    foreach ($stmt as $row) {
        if (password_verify($pin, $row['pin_hash'])) {
            $_SESSION['user_id']   = (int)$row['id'];
            $_SESSION['user_name'] = $row['name'];
            $_SESSION['user_role'] = $row['role'];
            $_SESSION['_pin_tries']    = 0;
            $_SESSION['_pin_lock_until'] = 0;
            session_regenerate_id(true);
            unset($row['pin_hash']);
            return $row;
        }
    }

    $tries++;
    $_SESSION['_pin_tries'] = $tries;
    if ($tries >= ($cfg['app']['max_pin_tries'] ?? 5)) {
        $_SESSION['_pin_lock_until'] = $now + ($cfg['app']['lock_seconds'] ?? 30);
        $_SESSION['_pin_tries'] = 0;
    }
    return null;
}

function logout(): void
{
    start_session_once();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    start_session_once();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'   => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'role' => $_SESSION['user_role'],
    ];
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        echo 'Forbidden — admins only.';
        exit;
    }
    return $u;
}

function csrf_token(): string
{
    start_session_once();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_check(): void
{
    start_session_once();
    $sent = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $sent)) {
        http_response_code(400);
        exit('Bad CSRF token.');
    }
}
