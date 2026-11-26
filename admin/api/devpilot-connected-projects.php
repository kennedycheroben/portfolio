<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../DevPilot/autoload.php';

use DevPilot\Website\WebsiteAuth;
use DevPilot\Website\WebsiteServices;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if (!$pdo instanceof PDO) { http_response_code(503); echo json_encode(['success'=>false,'error'=>'Hub database unavailable']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }
verify_csrf();
$ownerId = WebsiteAuth::currentAdminId();
$projectId = input_string($_POST,'project_id');
$action = input_string($_POST,'action');
try {
    if (!preg_match('/^[A-Za-z0-9._:-]{1,128}$/',$projectId) || !in_array($action,['refresh','disconnect'],true)) throw new RuntimeException('Project action is invalid.');
    $appEnvironment = (string) getenv('APP_ENV');
    $connectorEnabled = in_array($appEnvironment,['development','local','staging'],true) || ($appEnvironment==='production' && getenv('DEVPILOT_CONNECTOR_SECURITY_VERIFIED')==='1');
    if (!$connectorEnabled && $action === 'refresh') throw new RuntimeException('Hub project operations are disabled until the environment is approved.');
    if ($action === 'refresh') WebsiteServices::hubConnector($pdo)->refresh($ownerId,$projectId,time());
    else WebsiteServices::hubConnector($pdo)->disconnect($ownerId,$projectId,time());
    echo json_encode(['success'=>true,'message'=>$action === 'refresh' ? 'Authenticated inspection refreshed.' : 'Project authorization revoked.']);
} catch (Throwable $exception) {
    error_log('DevPilot connected-project action failed: ' . get_class($exception));
    http_response_code(422);
    echo json_encode(['success'=>false,'error'=>$exception instanceof RuntimeException ? $exception->getMessage() : 'Project action failed.']);
}
