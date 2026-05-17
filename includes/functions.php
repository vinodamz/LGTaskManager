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
    return "p-$p";
}
