<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'create') {
        $name = trim($_POST['name'] ?? '');
        $pin  = preg_replace('/\D/', '', $_POST['pin'] ?? '');
        $role = $_POST['role'] ?? 'staff';
        if ($name === '' || strlen($pin) < 4 || strlen($pin) > 6) {
            flash_set('error', 'Name and a 4–6 digit PIN are required.');
            redirect('admin.php');
        }
        if (!in_array($role, ['staff','admin'], true)) $role = 'staff';

        // Check PIN uniqueness — required because PIN alone is the credential.
        if (pin_is_in_use($pin)) {
            flash_set('error', 'That PIN is already in use. Pick another.');
            redirect('admin.php');
        }

        $stmt = db()->prepare("INSERT INTO users (name, pin_hash, role) VALUES (:n, :h, :r)");
        $stmt->execute([
            ':n' => $name,
            ':h' => password_hash($pin, PASSWORD_DEFAULT),
            ':r' => $role,
        ]);
        flash_set('ok', "User created. PIN: $pin");
        redirect('admin.php');
    }

    if ($op === 'update') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $role   = $_POST['role']   ?? 'staff';
        $active = !empty($_POST['active']) ? 1 : 0;
        $newPin = preg_replace('/\D/', '', $_POST['pin'] ?? '');

        if (!in_array($role, ['staff','admin'], true)) $role = 'staff';
        if ($id === $me['id'] && $role !== 'admin') {
            flash_set('error', "You can't demote yourself.");
            redirect('admin.php');
        }
        if ($id === $me['id'] && !$active) {
            flash_set('error', "You can't deactivate yourself.");
            redirect('admin.php');
        }

        if ($newPin !== '') {
            if (strlen($newPin) < 4 || strlen($newPin) > 6) {
                flash_set('error', 'PIN must be 4–6 digits.');
                redirect('admin.php');
            }
            if (pin_is_in_use($newPin, $id)) {
                flash_set('error', 'That PIN is already in use.');
                redirect('admin.php');
            }
            $stmt = db()->prepare("
                UPDATE users SET name=:n, role=:r, active=:a, pin_hash=:h WHERE id=:id
            ");
            $stmt->execute([
                ':n' => $name, ':r' => $role, ':a' => $active,
                ':h' => password_hash($newPin, PASSWORD_DEFAULT), ':id' => $id,
            ]);
        } else {
            $stmt = db()->prepare("
                UPDATE users SET name=:n, role=:r, active=:a WHERE id=:id
            ");
            $stmt->execute([':n' => $name, ':r' => $role, ':a' => $active, ':id' => $id]);
        }
        flash_set('ok', 'User updated.');
        redirect('admin.php');
    }

    if ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $me['id']) {
            flash_set('error', "You can't delete yourself.");
            redirect('admin.php');
        }
        $stmt = db()->prepare("DELETE FROM users WHERE id = :id");
        try {
            $stmt->execute([':id' => $id]);
            flash_set('ok', 'User deleted.');
        } catch (PDOException $e) {
            flash_set('error', 'Cannot delete: user has tasks they created. Deactivate instead.');
        }
        redirect('admin.php');
    }
}

function pin_is_in_use(string $pin, ?int $excludeUserId = null): bool
{
    $stmt = db()->query("SELECT id, pin_hash FROM users");
    foreach ($stmt as $row) {
        if ($excludeUserId !== null && (int)$row['id'] === $excludeUserId) continue;
        if (password_verify($pin, $row['pin_hash'])) return true;
    }
    return false;
}

$users = db()->query("SELECT id, name, role, active, created_at FROM users ORDER BY name")->fetchAll();

$pageTitle = 'Users — LG Task Manager';
include __DIR__ . '/includes/header.php';
?>

<h1>Users</h1>

<details class="task-form" open>
    <summary>+ Add user</summary>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="create">
        <div class="row">
            <label>Name<input name="name" required maxlength="100"></label>
            <label>PIN (4–6 digits)
                <input name="pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" required>
            </label>
            <label>Role
                <select name="role">
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </label>
        </div>
        <button class="btn btn-primary">Add user</button>
    </form>
</details>

<!-- Forms declared outside the table; inputs reference them via the HTML5 `form` attribute. -->
<?php foreach ($users as $u): ?>
    <form id="u-edit-<?= (int)$u['id'] ?>" method="post" hidden>
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="update">
        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
    </form>
    <form id="u-del-<?= (int)$u['id'] ?>" method="post" hidden
          onsubmit="return confirm('Delete this user? Their assigned tasks will be unassigned.')">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="delete">
        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
    </form>
<?php endforeach; ?>

<table class="tasks">
    <thead><tr><th>Name</th><th>Role</th><th>Active</th><th>Created</th><th>New PIN</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): $fid = 'u-edit-' . (int)$u['id']; ?>
        <tr>
            <td><input form="<?= $fid ?>" name="name" value="<?= e($u['name']) ?>" maxlength="100"></td>
            <td>
                <select form="<?= $fid ?>" name="role">
                    <option value="staff" <?= $u['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </td>
            <td>
                <label class="checkbox">
                    <input form="<?= $fid ?>" type="checkbox" name="active" value="1" <?= $u['active'] ? 'checked' : '' ?>>
                </label>
            </td>
            <td><?= e($u['created_at']) ?></td>
            <td>
                <input form="<?= $fid ?>" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" placeholder="leave blank">
            </td>
            <td class="row-actions">
                <button class="btn" form="<?= $fid ?>">Save</button>
                <button class="link-btn" form="u-del-<?= (int)$u['id'] ?>">Delete</button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include __DIR__ . '/includes/footer.php'; ?>
