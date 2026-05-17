<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
if (!$user) {
    redirect('login.php');
}

$pageTitle = 'Dashboard — LG Task Manager';

// Stats
$counts = db()->query("
    SELECT status, COUNT(*) AS n FROM tasks GROUP BY status
")->fetchAll();
$byStatus = ['todo' => 0, 'in_progress' => 0, 'done' => 0];
foreach ($counts as $r) $byStatus[$r['status']] = (int)$r['n'];

$mineStmt = db()->prepare("
    SELECT t.*, u.name AS assignee_name
    FROM tasks t
    LEFT JOIN users u ON u.id = t.assigned_to_user_id
    WHERE t.assigned_to_user_id = :uid AND t.status <> 'done'
    ORDER BY (t.due_date IS NULL), t.due_date ASC, t.priority DESC
    LIMIT 10
");
$mineStmt->execute([':uid' => $user['id']]);
$mine = $mineStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<h1>Dashboard</h1>

<div class="stats">
    <div class="stat"><span class="num"><?= $byStatus['todo'] ?></span> To do</div>
    <div class="stat"><span class="num"><?= $byStatus['in_progress'] ?></span> In progress</div>
    <div class="stat"><span class="num"><?= $byStatus['done'] ?></span> Done</div>
</div>

<h2>Your open tasks</h2>
<?php if (!$mine): ?>
    <p class="muted">Nothing assigned to you. <a href="tasks.php">See all tasks →</a></p>
<?php else: ?>
    <table class="tasks">
        <thead><tr><th>Title</th><th>Due</th><th>Priority</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($mine as $t): ?>
            <tr>
                <td><a href="tasks.php?edit=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a></td>
                <td><?= e($t['due_date'] ?: '—') ?></td>
                <td class="<?= priority_class($t['priority']) ?>"><?= e($t['priority']) ?></td>
                <td><?= e(status_label($t['status'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a class="btn" href="tasks.php">All tasks</a></p>

<?php include __DIR__ . '/includes/footer.php'; ?>
