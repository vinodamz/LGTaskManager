<?php
/**
 * migrate.php — one-shot schema migrator. Visit once after deploying a
 * release that bumps the schema, then delete the file.
 *
 * Only an authenticated admin can run migrations.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_admin();

header('Content-Type: text/plain; charset=utf-8');

$migrations = [
    '001_kanban'     => __DIR__ . '/sql/migrate_001_kanban.sql',
    '002_recurring'  => __DIR__ . '/sql/migrate_002_recurring.sql',
    '003_due_offset' => __DIR__ . '/sql/migrate_003_due_offset.sql',
];

foreach ($migrations as $name => $path) {
    echo "== Running $name ==\n";
    if (!is_readable($path)) {
        echo "  ✗ file not found: $path\n";
        continue;
    }
    $sql = file_get_contents($path);

    // Split on `DELIMITER //` … `DELIMITER ;` blocks so MySQL CALL works via
    // PDO's multi-statement execute().
    try {
        // Naive but correct enough: drop the DELIMITER lines and just run
        // the body in one batch — PDO emulates statements split by ;.
        // The `//` separators inside the procedure body stay intact because
        // we replace DELIMITER // with nothing and the // with ;.
        $clean = preg_replace('/^\s*DELIMITER\s+\S+\s*$/im', '', $sql);
        $clean = preg_replace('/\/\/\s*$/m', ';', $clean);

        $pdo = db();
        $pdo->exec($clean);
        echo "  ✓ ok\n";
    } catch (Throwable $e) {
        echo "  ✗ error: " . $e->getMessage() . "\n";
    }
}

echo "\nAll migrations attempted. ";
echo "Delete this file (migrate.php) from the server when you're done.\n";
