<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
if (!$user) {
    redirect('login.php');
}

$pageTitle = 'Dashboard — LG Task Manager';

// Stats
$counts = db()->query("SELECT status, COUNT(*) AS n FROM tasks GROUP BY status")->fetchAll();
$byStatus = ['todo' => 0, 'in_progress' => 0, 'done' => 0];
foreach ($counts as $r) $byStatus[$r['status']] = (int)$r['n'];

// My open tasks
$mineStmt = db()->prepare("
    SELECT t.*, u.name AS assignee_name
    FROM tasks t
    LEFT JOIN users u ON u.id = t.assigned_to_user_id
    WHERE t.assigned_to_user_id = :uid AND t.status <> 'done'
    ORDER BY (t.due_date IS NULL), t.due_date ASC, FIELD(t.priority,'high','normal','low')
    LIMIT 10
");
$mineStmt->execute([':uid' => $user['id']]);
$mine = $mineStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div>
        <p class="hero-eyebrow"><?= e(date('l, j M Y')) ?></p>
        <h1 class="hero-title">Hello, <?= e(first_name($user['name'])) ?>.</h1>
        <p class="hero-sub">Here&rsquo;s what&rsquo;s on your plate today.</p>
    </div>
    <div class="hero-stats">
        <div class="stat stat-p">
            <span class="n"><?= $byStatus['todo'] ?></span>
            <span class="l">To do</span>
        </div>
        <div class="stat stat-x">
            <span class="n"><?= $byStatus['in_progress'] ?></span>
            <span class="l">In progress</span>
        </div>
        <div class="stat stat-d">
            <span class="n"><?= $byStatus['done'] ?></span>
            <span class="l">Done</span>
        </div>
    </div>
</section>

<div class="actionbar">
    <h2 class="section-h">Your open tasks</h2>
    <a class="btn btn-primary" href="tasks.php"><span class="plus">+</span> All tasks</a>
</div>

<?php if (!$mine): ?>
    <div class="empty">
        Nothing assigned to you. <a href="tasks.php">Browse all tasks →</a>
    </div>
<?php else: ?>
    <ul class="task-list">
        <?php foreach ($mine as $t): ?>
            <li class="task status-<?= e($t['status']) ?>">
                <div class="task-head">
                    <span class="task-status-pill pill-<?= e($t['status']) ?>">
                        <?= e(status_label($t['status'])) ?>
                    </span>
                    <span class="task-priority <?= e(priority_class($t['priority'])) ?>">
                        <?= e($t['priority']) ?>
                    </span>
                    <?php if (!empty($t['due_date'])): ?>
                        <span class="task-due">Due <?= e($t['due_date']) ?></span>
                    <?php endif; ?>
                </div>
                <h3 class="task-title">
                    <a href="tasks.php?edit=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
                </h3>
                <?php if (!empty($t['description'])): ?>
                    <p class="task-desc"><?= nl2br(e($t['description'])) ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
