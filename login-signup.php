<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit('Method not allowed.'); }
redirect(site_url('login.php'));
