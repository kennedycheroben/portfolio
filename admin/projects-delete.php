<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
verify_csrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Invalid project.');
}

$statement = $pdo->prepare('SELECT image_url FROM projects WHERE id = ? LIMIT 1');
$statement->execute([$id]);
$project = $statement->fetch();
if (!$project) {
    http_response_code(404);
    exit('Project not found.');
}

$pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
$uploads = realpath(__DIR__ . '/../uploads/projects');
$candidate = realpath(__DIR__ . '/../' . ltrim((string) $project['image_url'], '/'));
if ($uploads && $candidate && str_starts_with($candidate, $uploads . DIRECTORY_SEPARATOR) && is_file($candidate)) {
    unlink($candidate);
}

redirect('projects.php?deleted=1');
