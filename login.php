<?php
declare(strict_types=1);

define('DB_OPTIONAL', true);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? site_url('admin/index.php') : site_url('index.php'));
}

$error = '';
$databaseAvailable = $pdo instanceof PDO;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identifier = input_string($_POST, 'username');
    $password = input_string($_POST, 'password', false);
    $startedAt = filter_input(INPUT_POST, 'started_at', FILTER_VALIDATE_INT);

    $allowed = rate_limit('login-ip', 10, 900) && rate_limit('login-account', 6, 900, $identifier);
    if (!$databaseAvailable) {
        http_response_code(503);
        $error = 'Administrator sign-in is temporarily unavailable. Start the database service and try again.';
    } elseif (!$allowed) {
        $error = 'Unable to sign in. Please wait and try again.';
    } elseif (input_string($_POST, 'website') !== '' || !$startedAt || time() - $startedAt < 1) {
        $error = 'Unable to sign in with those details.';
    } elseif ($identifier === '' || text_length($identifier) > 160 || $password === '' || strlen($password) > 4096) {
        $error = 'Unable to sign in with those details.';
    } else {
        $statement = $pdo->prepare('SELECT id, username, email, password_hash, role FROM users WHERE username = ? OR email = ? LIMIT 1');
        $statement->execute([$identifier, $identifier]);
        $user = $statement->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_username'] = (string) $user['username'];
            $_SESSION['user_email'] = (string) $user['email'];
            $_SESSION['user_role'] = (string) $user['role'];
            $_SESSION['session_started'] = time();
            $_SESSION['last_activity'] = time();
            redirect($user['role'] === 'admin' ? site_url('admin/index.php') : site_url('index.php'));
        }
        password_verify($password, '$2y$10$JQ5oqMOQiGQKfsN.4Evj4u6TzwYnssRa2jylQ4h6s.8tE5.ZZXlWq');
        $error = 'Unable to sign in with those details.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Administrator sign in — Kennedy Cheroben</title><link rel="icon" type="image/x-icon" href="<?= e(site_url('favicon.ico')) ?>"><link rel="icon" type="image/webp" href="<?= e(site_url('assets/img/cheroben_logo.webp')) ?>"><link rel="apple-touch-icon" href="<?= e(site_url('assets/img/cheroben_logo.webp')) ?>"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="<?= e(site_url('assets/css/main.css?v=20260911_2')) ?>"></head>
<body class="auth-page"><main class="auth-card"><a class="auth-brand" href="<?= e(site_url('index.php')) ?>">Kennedy Cheroben</a><p class="eyebrow">Private administration</p><h1>Welcome back.</h1><p>Sign in to manage portfolio content.</p><?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php elseif (!$databaseAvailable): ?><div class="alert alert-danger" role="alert">Administrator sign-in is temporarily unavailable. Start the database service and try again.</div><?php endif; ?><form method="post" novalidate><?= csrf_field() ?><input type="hidden" name="started_at" value="<?= time() ?>"><div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><label>Username or email<input type="text" name="username" maxlength="160" autocomplete="username" required autofocus <?= !$databaseAvailable ? 'disabled' : '' ?>></label><label>Password<input type="password" name="password" autocomplete="current-password" required <?= !$databaseAvailable ? 'disabled' : '' ?>></label><button class="button" type="submit" <?= !$databaseAvailable ? 'disabled' : '' ?>>Sign in <i class="bi bi-arrow-right"></i></button></form><p class="auth-note"><a href="<?= e(site_url('index.php')) ?>">Return to the portfolio</a></p></main></body></html>
