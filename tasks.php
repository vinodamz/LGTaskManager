<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$action = $_GET['action'] ?? 'list';

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
        if (!in_array($status, ['todo','in_progress','done'], true))      $status = 'todo';
        if (!in_array($priority, ['low','normal','high'], true))          $priority = 'normal';

        if ($op === 'create') {
            $stmt = db()->prepare("
                INSERT INTO tasks (title, description, status, priority, due_date,
                                   assigned_to_user_id, created_by_user_id)
                VALUES (:t, :d, :s, :p, :due, :a, :c)
            ");
            $stmt->execute([
                ':t' => $title, ':d' => $description, ':s' => $status, ':p' => $priority,
                ':due' => $due, ':a' => $assignee, ':c' => $user['id'],
            ]);
            flash_set('ok', 'Task created.');
        } else {
            $stmt = db()->prepare("
                UPDATE tasks SET title=:t, description=:d, status=:s, priority=:p,
                                 due_date=:due, assigned_to_user_id=:a
                WHERE id=:id
            ");
            $stmt->execute([
                ':t' => $title, ':d' => $description, ':s' => $status, ':p' => $priority,
                ':due' => $due, ':a' => $assignee, ':id' => $id,
            ]);
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
        redirect('tasks.php' . (isset($_POST['return']) ? '?' . $_POST['return'] : ''));
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
    ORDER BY t.status = 'done', (t.due_date IS NULL), t.due_date ASC, t.priority DESC, t.id DESC
";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$pageTitle = 'Tasks — LG Task Manager';
include __DIR__ . '/includes/header.php';
?>

<h1>Tasks</h1>

<form class="filters" method="get">
    <input type="search" name="q" placeholder="Search…" value="<?= e($search) ?>">
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
    <a class="btn" href="tasks.php">Reset</a>
</form>

<details class="task-form" <?= $editing ? 'open' : '' ?>>
    <summary><?= $editing ? 'Edit task' : '+ New task' ?></summary>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
        <?php endif; ?>

        <label>Title
            <input name="title" required maxlength="200" value="<?= e($editing['title'] ?? '') ?>">
        </label>

        <label>Description
            <textarea name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
        </label>

        <div class="row">
            <label>Status
                <select name="status">
                    <?php foreach (['todo','in_progress','done'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($editing['status'] ?? 'todo') === $s ? 'selected' : '' ?>>
                            <?= status_label($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Priority
                <select name="priority">
                    <?php foreach (['low','normal','high'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($editing['priority'] ?? 'normal') === $p ? 'selected' : '' ?>>
                            <?= ucfirst($p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Due date
                <input type="date" name="due_date" value="<?= e($editing['due_date'] ?? '') ?>">
            </label>

            <label>Assigned to
                <select name="assigned_to_user_id">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"
                            <?= isset($editing['assigned_to_user_id']) && (int)$editing['assigned_to_user_id'] === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= e($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="actions">
            <button class="btn btn-primary"><?= $editing ? 'Save' : 'Create task' ?></button>
            <?php if ($editing): ?>
                <a class="btn" href="tasks.php">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</details>

<?php if (!$tasks): ?>
    <p class="muted">No tasks match your filters.</p>
<?php else: ?>
    <table class="tasks">
        <thead>
            <tr>
                <th>Title</th><th>Status</th><th>Priority</th>
                <th>Due</th><th>Assignee</th><th>Created by</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tasks as $t): ?>
            <tr class="task-row status-<?= e($t['status']) ?>">
                <td>
                    <a href="tasks.php?edit=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
                    <?php if (!empty($t['description'])): ?>
                        <div class="desc"><?= nl2br(e($t['description'])) ?></div>
                    <?php endif; ?>
                </td>
                <td>
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
                </td>
                <td class="<?= priority_class($t['priority']) ?>"><?= e($t['priority']) ?></td>
                <td><?= e($t['due_date'] ?: '—') ?></td>
                <td><?= e($t['assignee_name'] ?: '—') ?></td>
                <td><?= e($t['creator_name'] ?: '—') ?></td>
                <td class="row-actions">
                    <a href="tasks.php?edit=<?= (int)$t['id'] ?>">Edit</a>
                    <form method="post" class="inline" onsubmit="return confirm('Delete this task?')">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="op" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <button class="link-btn">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
