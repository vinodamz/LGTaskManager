<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

// =========================================================================
// AJAX endpoints — POSTs that return JSON. Browser navigates use HTML below.
// =========================================================================
function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    // ---------- Kanban: move card to (column, position) ----------
    if ($op === 'move' && $isAjax) {
        $id      = (int)($_POST['id'] ?? 0);
        $colId   = (int)($_POST['column_id'] ?? 0);
        $pos     = max(0, (int)($_POST['position'] ?? 0));
        if ($id <= 0 || $colId <= 0) json_out(['ok' => false, 'error' => 'bad input'], 400);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            // shift cards in destination column at >= $pos one slot down
            $shift = $pdo->prepare("
                UPDATE tasks SET board_position = board_position + 1
                WHERE column_id = :c AND board_position >= :p AND id <> :id
            ");
            $shift->execute([':c' => $colId, ':p' => $pos, ':id' => $id]);

            // place the moved card
            $set = $pdo->prepare("
                UPDATE tasks SET column_id = :c, board_position = :p WHERE id = :id
            ");
            $set->execute([':c' => $colId, ':p' => $pos, ':id' => $id]);

            // normalise positions (0..n-1) in the destination column
            $rows = $pdo->prepare("SELECT id FROM tasks WHERE column_id = :c ORDER BY board_position, id");
            $rows->execute([':c' => $colId]);
            $upd = $pdo->prepare("UPDATE tasks SET board_position = :p WHERE id = :id");
            $i = 0;
            foreach ($rows as $r) {
                $upd->execute([':p' => $i++, ':id' => (int)$r['id']]);
            }

            $pdo->commit();
            json_out(['ok' => true]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            json_out(['ok' => false, 'error' => 'db: ' . $e->getMessage()], 500);
        }
    }

    // ---------- Form: create / update / delete ----------
    if ($op === 'create' || $op === 'update') {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $colId       = isset($_POST['column_id']) ? (int)$_POST['column_id'] : 0;
        $priority    = $_POST['priority'] ?? 'normal';
        $due         = $_POST['due_date'] ?: null;
        $assignee    = isset($_POST['assigned_to_user_id']) && $_POST['assigned_to_user_id'] !== ''
                       ? (int)$_POST['assigned_to_user_id'] : null;

        if ($title === '') {
            flash_set('error', 'Title is required.');
            redirect('tasks.php');
        }
        if (!in_array($priority, ['low','normal','high'], true)) $priority = 'normal';

        // Default column if none picked (first column by position)
        if ($colId <= 0 && kanban_available()) {
            $first = task_columns()[0] ?? null;
            $colId = $first ? (int)$first['id'] : 0;
        }

        if ($op === 'create') {
            // place at end of selected column
            $maxPos = 0;
            if ($colId > 0) {
                $q = db()->prepare("SELECT COALESCE(MAX(board_position), -1) + 1 FROM tasks WHERE column_id = :c");
                $q->execute([':c' => $colId]);
                $maxPos = (int) $q->fetchColumn();
            }
            $stmt = db()->prepare("
                INSERT INTO tasks (title, description, status, column_id, board_position, priority,
                                   due_date, assigned_to_user_id, created_by_user_id)
                VALUES (:t, :d, :s, :col, :pos, :p, :due, :a, :c)
            ");
            $stmt->execute([
                ':t' => $title, ':d' => $description,
                ':s' => 'todo', // legacy column, kept for compat
                ':col' => $colId ?: null, ':pos' => $maxPos,
                ':p' => $priority, ':due' => $due, ':a' => $assignee, ':c' => $user['id'],
            ]);
            flash_set('ok', 'Task created.');
        } else {
            $stmt = db()->prepare("
                UPDATE tasks SET title=:t, description=:d, column_id=:col, priority=:p,
                                 due_date=:due, assigned_to_user_id=:a
                WHERE id=:id
            ");
            $stmt->execute([
                ':t' => $title, ':d' => $description, ':col' => $colId ?: null,
                ':p' => $priority, ':due' => $due, ':a' => $assignee, ':id' => $id,
            ]);
            flash_set('ok', 'Task updated.');
        }
        redirect('tasks.php?' . http_build_query(array_diff_key($_GET, ['edit' => 1])));
    }

    if ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare("DELETE FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        flash_set('ok', 'Task deleted.');
        redirect('tasks.php');
    }

    if ($op === 'quick_status') {
        // Used by the list view's per-row column picker
        $id    = (int)($_POST['id'] ?? 0);
        $colId = (int)($_POST['column_id'] ?? 0);
        if ($id > 0 && $colId > 0) {
            $maxPos = 0;
            $q = db()->prepare("SELECT COALESCE(MAX(board_position), -1) + 1 FROM tasks WHERE column_id = :c");
            $q->execute([':c' => $colId]);
            $maxPos = (int) $q->fetchColumn();
            $stmt = db()->prepare("UPDATE tasks SET column_id = :c, board_position = :p WHERE id = :id");
            $stmt->execute([':c' => $colId, ':p' => $maxPos, ':id' => $id]);
        }
        redirect('tasks.php' . (!empty($_POST['return']) ? '?' . $_POST['return'] : ''));
    }
}

// =========================================================================
// View setup
// =========================================================================
$users = db()->query("SELECT id, name FROM users WHERE active = 1 ORDER BY name")->fetchAll();
$cols  = task_columns();
$hasKanban = $cols !== [];

$view = $_GET['view'] ?? ($hasKanban ? 'board' : 'list');
if (!in_array($view, ['board', 'list'], true)) $view = 'list';
if ($view === 'board' && !$hasKanban) $view = 'list';

$editing = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare("SELECT * FROM tasks WHERE id = :id");
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

// Filters
$filterAssn = $_GET['assignee'] ?? '';
$filterCol  = $_GET['col']      ?? '';
$search     = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($filterAssn === 'me')                       { $where[] = 't.assigned_to_user_id = :me'; $params[':me'] = $user['id']; }
elseif ($filterAssn !== '' && ctype_digit($filterAssn)) { $where[] = 't.assigned_to_user_id = :a'; $params[':a'] = (int)$filterAssn; }
if ($filterCol !== '' && ctype_digit($filterCol)) { $where[] = 't.column_id = :col'; $params[':col'] = (int)$filterCol; }
if ($search !== '') { $where[] = '(t.title LIKE :q OR t.description LIKE :q)'; $params[':q'] = '%'.$search.'%'; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT t.*, u.name AS assignee_name, c.name AS creator_name,
           col.name AS column_name, col.color AS column_color, col.is_done AS column_done
    FROM tasks t
    LEFT JOIN users u        ON u.id   = t.assigned_to_user_id
    LEFT JOIN users c        ON c.id   = t.created_by_user_id
    LEFT JOIN task_columns col ON col.id = t.column_id
    $whereSql
    ORDER BY col.position ASC, t.board_position ASC, t.id DESC
";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Group tasks per column for the board view.
$byColumn = [];
foreach ($cols as $col) $byColumn[$col['id']] = [];
foreach ($tasks as $t) {
    if (!empty($t['column_id']) && isset($byColumn[$t['column_id']])) {
        $byColumn[$t['column_id']][] = $t;
    }
}

$pageTitle = 'Tasks — LG Task Manager';
include __DIR__ . '/includes/header.php';
?>

<div class="actionbar">
    <h1>Tasks</h1>
    <?php if ($hasKanban): ?>
        <div class="view-toggle">
            <?php $qs = $_GET; unset($qs['view']); $qs['view'] = 'board'; ?>
            <a class="toggle <?= $view === 'board' ? 'active' : '' ?>" href="?<?= e(http_build_query($qs)) ?>">Board</a>
            <?php $qs['view'] = 'list'; ?>
            <a class="toggle <?= $view === 'list' ? 'active' : '' ?>" href="?<?= e(http_build_query($qs)) ?>">List</a>
        </div>
    <?php endif; ?>
</div>

<form class="filters" method="get">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <input type="search" name="q" placeholder="Search tasks…" value="<?= e($search) ?>">
    <?php if ($hasKanban): ?>
        <select name="col">
            <option value="">Any column</option>
            <?php foreach ($cols as $col): ?>
                <option value="<?= (int)$col['id'] ?>" <?= (string)$col['id'] === (string)$filterCol ? 'selected' : '' ?>><?= e($col['name']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <select name="assignee">
        <option value="">Anyone</option>
        <option value="me" <?= $filterAssn === 'me' ? 'selected' : '' ?>>Me</option>
        <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (string)$u['id'] === (string)$filterAssn ? 'selected' : '' ?>><?= e($u['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn">Filter</button>
    <a class="btn btn-ghost" href="tasks.php?view=<?= e($view) ?>">Reset</a>
</form>

<details class="card card-form" <?= $editing ? 'open' : '' ?>>
    <summary><?= $editing ? 'Edit task' : 'New task' ?></summary>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

        <div class="field">
            <label>Title</label>
            <input name="title" required maxlength="200" value="<?= e($editing['title'] ?? '') ?>">
        </div>
        <div class="field">
            <label>Description</label>
            <textarea name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
        </div>
        <div class="row">
            <?php if ($hasKanban): ?>
            <div class="field">
                <label>Column</label>
                <select name="column_id">
                    <?php foreach ($cols as $col): ?>
                        <option value="<?= (int)$col['id'] ?>"
                            <?= isset($editing['column_id']) && (int)$editing['column_id'] === (int)$col['id'] ? 'selected' : '' ?>>
                            <?= e($col['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="field">
                <label>Priority</label>
                <select name="priority">
                    <?php foreach (['low','normal','high'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($editing['priority'] ?? 'normal') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
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
                            <?= isset($editing['assigned_to_user_id']) && (int)$editing['assigned_to_user_id'] === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="actions">
            <button class="btn btn-primary"><?= $editing ? 'Save' : 'Create task' ?></button>
            <?php if ($editing): ?><a class="btn btn-ghost" href="tasks.php?view=<?= e($view) ?>">Cancel</a><?php endif; ?>
        </div>
    </form>
</details>

<?php if ($view === 'board' && $hasKanban): ?>
    <!-- ============================ BOARD VIEW ============================ -->
    <div class="board" data-csrf="<?= e(csrf_token()) ?>">
        <?php foreach ($cols as $col):
            $list = $byColumn[$col['id']] ?? [];
        ?>
            <section class="board-col" data-col-id="<?= (int)$col['id'] ?>" style="--col: <?= e($col['color']) ?>;">
                <header class="board-col-head">
                    <span class="board-col-dot"></span>
                    <span class="board-col-name"><?= e($col['name']) ?></span>
                    <span class="board-col-count"><?= count($list) ?></span>
                </header>
                <ul class="board-col-list" data-col-id="<?= (int)$col['id'] ?>">
                    <?php foreach ($list as $t): ?>
                        <li class="board-card" data-task-id="<?= (int)$t['id'] ?>">
                            <div class="board-card-pills">
                                <span class="task-priority <?= e(priority_class($t['priority'])) ?>"><?= e($t['priority']) ?></span>
                                <?php if (!empty($t['due_date'])): ?>
                                    <span class="task-due">Due <?= e($t['due_date']) ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="board-card-title">
                                <a href="tasks.php?view=<?= e($view) ?>&edit=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
                            </h3>
                            <?php if (!empty($t['description'])): ?>
                                <p class="board-card-desc"><?= e(mb_strimwidth($t['description'], 0, 110, '…')) ?></p>
                            <?php endif; ?>
                            <p class="board-card-foot">
                                <span><?= e($t['assignee_name'] ?: 'Unassigned') ?></span>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>window.LGTM_CSRF = <?= json_encode(csrf_token()) ?>;</script>
    <script src="assets/js/kanban.js?v=<?= e(asset_version()) ?>"></script>

<?php else: ?>
    <!-- ============================ LIST VIEW ============================= -->
    <?php if (!$tasks): ?>
        <div class="empty">No tasks match your filters.</div>
    <?php else: ?>
        <ul class="task-list">
            <?php foreach ($tasks as $t): $colColor = $t['column_color'] ?? '#EC407A'; ?>
                <li class="task" style="border-left-color: <?= e($colColor) ?>;">
                    <div class="task-head">
                        <span class="task-status-pill" style="background: <?= e($colColor) ?>22; color: <?= e($colColor) ?>;">
                            <?= e($t['column_name'] ?? $t['status']) ?>
                        </span>
                        <span class="task-priority <?= e(priority_class($t['priority'])) ?>"><?= e($t['priority']) ?></span>
                        <?php if (!empty($t['due_date'])): ?><span class="task-due">Due <?= e($t['due_date']) ?></span><?php endif; ?>
                    </div>
                    <h3 class="task-title">
                        <a href="tasks.php?view=<?= e($view) ?>&edit=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
                    </h3>
                    <?php if (!empty($t['description'])): ?>
                        <p class="task-desc"><?= nl2br(e($t['description'])) ?></p>
                    <?php endif; ?>
                    <p class="task-by">
                        <?= e($t['assignee_name'] ? 'Assigned to ' . $t['assignee_name'] : 'Unassigned') ?>
                        · created by <?= e($t['creator_name'] ?? '—') ?>
                    </p>
                    <div class="task-actions">
                        <?php if ($hasKanban): ?>
                        <form method="post" class="inline">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="op" value="quick_status">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="return" value="<?= e(http_build_query($_GET)) ?>">
                            <select name="column_id" onchange="this.form.submit()">
                                <?php foreach ($cols as $col): ?>
                                    <option value="<?= (int)$col['id'] ?>" <?= (int)($t['column_id'] ?? 0) === (int)$col['id'] ? 'selected' : '' ?>>
                                        <?= e($col['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php endif; ?>
                        <a class="btn btn-ghost" href="tasks.php?view=<?= e($view) ?>&edit=<?= (int)$t['id'] ?>">Edit</a>
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
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
