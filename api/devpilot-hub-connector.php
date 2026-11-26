<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../DevPilot/autoload.php';

use DevPilot\Website\WebsiteServices;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }
if (!$pdo instanceof PDO) { http_response_code(503); echo json_encode(['success'=>false,'error'=>'Hub database unavailable']); exit; }
$rawBody = file_get_contents('php://input', false, null, 0, 8193);
if (!is_string($rawBody) || strlen($rawBody) > 8192) { http_response_code(413); echo json_encode(['success'=>false,'error'=>'Request is too large']); exit; }
$request = json_decode($rawBody, true);
if (!is_array($request)) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid pairing request']); exit; }
try {
    // Touching the configured value ensures Hub deployments use explicit config, never APP_URL fallback.
    \DevPilot\Connector\ConnectorPolicy::configuredHubUrl();
    $id = WebsiteServices::hubPairings($pdo)->create($request, (string) ($request['pairing_code'] ?? ''), (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), time());
    echo json_encode(['success'=>true,'request_id'=>$id], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('DevPilot Hub pairing request failed: ' . get_class($exception));
    http_response_code(422);
    echo json_encode(['success'=>false,'error'=>$exception instanceof InvalidArgumentException || $exception instanceof RuntimeException ? $exception->getMessage() : 'Pairing request was rejected.']);
}
