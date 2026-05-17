<?php
// Misc view helpers.

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash_set(string $type, string $msg): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

function status_label(string $s): string
{
    return [
        'todo'        => 'To do',
        'in_progress' => 'In progress',
        'done'        => 'Done',
    ][$s] ?? $s;
}

function priority_class(string $p): string
{
    return "priority-$p";
}

/**
 * Pick a stable brand colour for a user, based on their id.
 * Used to tint profile avatars / team dots.
 */
function user_color(int $id): string
{
    static $palette = ['#EC407A', '#5BA547', '#F5B342', '#2D6BA0', '#A05C7B', '#5DA8A2', '#E07A5F', '#7E57C2'];
    return $palette[$id % count($palette)];
}

/**
 * One- or two-letter initials from a display name.
 */
function user_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/', $name);
    if (count($parts) === 1) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1));
    }
    return mb_strtoupper(
        mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1)
    );
}

function first_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    return $parts[0] ?? $name;
}

/**
 * Cache-busting version string for assets. Uses the mtime of style.css —
 * any deploy that updates it bumps the query string and forces browsers
 * to fetch the new file.
 */
function asset_version(): string
{
    static $v = null;
    if ($v === null) {
        $css = __DIR__ . '/../assets/css/style.css';
        $v = is_readable($css) ? (string) filemtime($css) : '1';
    }
    return $v;
}

/**
 * All task columns ordered for board rendering. Returns [] if the kanban
 * migration hasn't run yet — callers should fall back to the list view.
 */
function task_columns(): array
{
    static $cols = null;
    if ($cols === null) {
        try {
            $cols = db()->query("
                SELECT id, name, position, color, is_done
                FROM task_columns
                ORDER BY position ASC, id ASC
            ")->fetchAll();
        } catch (Throwable $e) {
            $cols = [];
        }
    }
    return $cols;
}

function kanban_available(): bool
{
    return task_columns() !== [];
}
