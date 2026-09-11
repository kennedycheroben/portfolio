<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isLoggedIn() && hash_equals('admin', (string) ($_SESSION['user_role'] ?? ''));
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        redirect(site_url('login.php'));
    }
}
