<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Method not allowed.'); }
verify_csrf();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $parameters = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $parameters['path'],
        'domain' => $parameters['domain'],
        'secure' => (bool) $parameters['secure'],
        'httponly' => (bool) $parameters['httponly'],
        'samesite' => $parameters['samesite'] ?: 'Lax',
    ]);
}
session_destroy();
redirect(site_url('index.php'));
