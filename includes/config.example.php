<?php
// Copy this file to config.php and fill in the real values.
// config.php is gitignored — never commit DB credentials.

return [
    'db' => [
        'host'     => 'localhost',
        'name'     => 'cpaneluser_lgtasks',   // cPanel prefixes DB names with your account
        'user'     => 'cpaneluser_lgtasks',
        'password' => 'CHANGE_ME',
        'charset'  => 'utf8mb4',
    ],
    'app' => [
        'name'           => 'LG Task Manager',
        'session_name'   => 'LGTM_SESSION',
        'max_pin_tries'  => 5,        // lock the session for a few seconds after this many bad PINs
        'lock_seconds'   => 30,
        'timezone'       => 'Asia/Kolkata',
    ],
];
