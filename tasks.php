<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

// ------------- POST handlers -------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'create' || $op === 'update') {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status      = $_POST['status']   ?? 'todo';
        $priority    = $_POST['priority'] ?? 'normal';
        $due         = $_POST['due_date'] ?: null;
        $assignee    = $_POST['assigned_to_user_id'] !== '' ? (int)$_POST['assigned_to_user_id'] : null;

        if ($title === '') {
            flash_set('error', 'Title is required.');
            redirect('tasks.php');
        }
        if (!in_array($status,   ['todo','in_progress','done'], true)) $status   = 'todo';
        if (!in_array($priority, ['low','normal','high'],       true)) $priority = 'normal';

        if ($op === 'create') {
            $stmt = db()->prepare("
                INSERT INTO tasks (title, description, status, priority, due_date,
                                   assigned_to_user_id, created_by_user_id)
                VALUES (:t, :d, :s, :p, :due, :a, :c)
            ");
            $stmt->execute([':t' => $title, ':d' => $description, ':s' => $status, ':p' => $priority,
                            ':due' => $due, ':a' => $assignee, ':c' => $user['id']]);
            flash_set('ok', 'Task created.');
        } else {
            $stmt = db()->prepare("
                UPDATE tasks SET title=:t, description=:d, status=:s, priority=:p,
                                 due_date=:due, assigned_to_user_id=:a
                WHERE id=:id
            ");
            $stmt->execute([':t' => $title, ':d' => $description, ':s' => $status, ':p' => $priority,
                            ':due' => $due, ':a' => $assignee, ':id' => $id]);
            flash_set('ok', 'Task updated.');
        }
        redirect('tasks.php');
    }

    if ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare("DELETE FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        flash_set('ok', 'Task deleted.');
        redirect('tasks.php');
    }

    if ($op === 'quick_status') {
        $id = (int)($_POST['id'] ?? 0);
        $s  = $_POST['status'] ?? 'todo';
        if (!in_array($s, ['todo','in_progress','done'], true)) $s = 'todo';
        $stmt = db()->prepare("UPDATE tasks SET status = :s WHERE id = :id");
        $stmt->execute([':s' => $s, ':id' => $id]);
        redirect('tasks.php' . (!empty($_POST['return']) ? '?' . $_POST['return'] : ''));
    }
}

// ------------- Load data -------------
$users = db()->query("SELECT id, name FROM users WHERE active = 1 ORDER BY name")->fetchAll();

$editing = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare("SELECT * FROM tasks WHERE id = :id");
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

// Filters
$filterStatus = $_GET['status']   ?? '';
$filterAssn   = $_GET['assignee'] ?? '';
$search       = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if (in_array($filterStatus, ['todo','in_progress','done'], true)) {
    $where[] = 't.status = :st';
    $params[':st'] = $filterStatus;
}
if ($filterAssn === 'me') {
    $where[] = 't.assigned_to_user_id = :me';
    $params[':me'] = $user['id'];
} elseif ($filterAssn !== '' && ctype_digit($filterAssn)) {
    $where[] = 't.assigned_to_user_id = :a';
    $params[':a'] = (int)$filterAssn;
}
if ($search !== '') {
    $where[] = '(t.title LIKE :q OR t.description LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT t.*, u.name AS assignee_name, c.name AS creator_name
    FROM tasks t
    LEFT JOIN users u ON u.id = t.assigned_to_user_id
    LEFT JOIN users c ON c.id = t.created_by_user_id
    $whereSql
    ORDER BY t.status = 'done', (t.due_date IS NULL), t.due_date ASC,
             FIELD(t.priority,'high','normal','low'), t.id DESC
";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$pageTitle = 'Tasks — LG Task Manager';
include __DIR__ . '/includes/header.php';
?>

<div class="actionbar">
    <h1>Tasks</h1>
</div>

<form class="filters" method="get">
    <input type="search" name="q" placeholder="Search tasks…" value="<?= e($search) ?>">
    <select name="status">
        <option value="">Any status</option>
        <?php foreach (['todo','in_progress','done'] as $s): ?>
            <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= status_label($s) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="assignee">
        <option value="">Anyone</option>
        <option value="me" <?= $filterAssn === 'me' ? 'selected' : '' ?>>Me</option>
        <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (string)$u['id'] === (string)$filterAssn ? 'selected' : '' ?>>
                <?= e($u['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn">Filter</button>
    <a class="btn btn-ghost" href="tasks.php">Reset</a>
</form>

<details class="card card-form" <?= $editing ? 'open' : '' ?>>
    <summary><?= $editing ? 'Edit task' : 'New task' ?></summary>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
        <?php endif; ?>

        <div class="field">
            <label>Title</label>
            <input name="title" required maxlength="200" value="<?= e($editing['title'] ?? '') ?>">
        </div>

        <div class="field">
            <label>Description</label>
            <textarea name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
        </div>

        <div class="row">
            <div class="field">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['todo','in_progress','done'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($editing['status'] ?? 'todo') === $s ? 'selected' : '' ?>>
                            <?= status_label($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Priority</label>
                <select name="priority">
                    <?php foreach (['low','normal','high'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($editing['priority'] ?? 'normal') === $p ? 'selected' : '' ?>>
                            <?= ucfirst($p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Due date</label>
                <input type="date" name="due_date" value="<?= e($editing['due_date'] ?? '') ?>">
            </div>

            <div class="field">
                <label>Assigned to</label>
                <select name="assigned_to_user_id">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"
                            <?= isset($editing['assigned_to_user_id']) && (int)$editing['assigned_to_user_id'] === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= e($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="actions">
            <button class="btn btn-primary"><?= $editing ? 'Save' : 'Create task' ?></button>
            <?php if ($editing): ?>
                <a class="btn btn-ghost" href="tasks.php">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</details>

<?php if (!$tasks): ?>
    <div class="empty">No tasks match your filters.</div>
<?php else: ?>
    <ul class="task-list">
        <?php foreach ($tasks as $t): ?>
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
                <p class="task-by">
                    <?= e($t['assignee_name'] ? 'Assigned to ' . $t['assignee_name'] : 'Unassigned') ?>
                    · created by <?= e($t['creator_name'] ?? '—') ?>
                </p>
                <div class="task-actions">
                    <form method="post" class="inline">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="op" value="quick_status">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <input type="hidden" name="return" value="<?= e(http_build_query($_GET)) ?>">
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach (['todo','in_progress','done'] as $s): ?>
                                <option value="<?= $s ?>" <?= $t['status'] === $s ? 'selected' : '' ?>>
                                    <?= status_label($s) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <a class="btn btn-ghost" href="tasks.php?edit=<?= (int)$t['id'] ?>">Edit</a>
                    <form method="post" class="inline" onsubmit="return confirm('Delete this task?')">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="op" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <button class="link-btn">Delete</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
