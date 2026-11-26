<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/devpilot-loader.php';
devpilot_register_autoloader();

use DevPilot\Connector\ConnectorHttpClient;
use DevPilot\Connector\ConnectorPolicy;
use DevPilot\Website\WebsiteAuth;
use DevPilot\Website\WebsiteServices;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
if (!$pdo instanceof PDO) { http_response_code(503); echo json_encode(['success'=>false,'error'=>'Connector unavailable']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }
verify_csrf();

$identityOrigin = rtrim((string) (getenv('APP_URL') ?: ''), '/');
$projectId = 'php-' . substr(hash('sha256', $identityOrigin . '|' . dirname(__DIR__, 2)), 0, 24);
$projectName = 'Portfolio project';
$ownerId = WebsiteAuth::currentAdminId();
$action = input_string($_POST, 'action') ?: 'connect';
$service = WebsiteServices::connector($pdo);
$http = new ConnectorHttpClient();

try {
    $appEnvironment = (string) getenv('APP_ENV');
    $connectorPermitted = in_array($appEnvironment, ['development','local','staging'], true)
        || ($appEnvironment === 'production' && getenv('DEVPILOT_CONNECTOR_SECURITY_VERIFIED') === '1');
    if (!$connectorPermitted && $action !== 'disconnect') {
        throw new RuntimeException('The connector is disabled until its environment is explicitly approved.');
    }
    $origin = ConnectorPolicy::origin($identityOrigin);
    $hubUrl = ConnectorPolicy::configuredHubUrl();
    if (hash_equals($origin, $hubUrl)) throw new RuntimeException('DEVPILOT_HUB_URL must point to a separately hosted DevPilot Hub.');
    if ($action === 'connect') {
        $pairing = $service->beginPairing($projectId, $projectName, $origin, [ConnectorPolicy::INSPECT], time(), $ownerId);
        $request = $http->post($hubUrl, '/api/devpilot-hub-connector.php?action=request', null, [
            'pairing_code'=>$pairing['pairing_code'], 'project_id'=>$projectId, 'project_name'=>$projectName,
            'origin'=>$origin, 'scopes'=>[ConnectorPolicy::INSPECT], 'return_url'=>$origin . '/admin/devpilot-websites.php',
        ]);
        $requestId = (string) ($request['request_id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $requestId)) throw new RuntimeException('The DevPilot Hub returned an invalid approval request.');
        echo json_encode(['success'=>true,'message'=>'Approve this project in DevPilot Hub to finish connecting.','approval_url'=>$hubUrl . '/admin/devpilot-connector-approve.php?request=' . $requestId], JSON_THROW_ON_ERROR);
        exit;
    }
    if (in_array($action, ['refresh','disconnect'], true)) {
        $projectUrl = $hubUrl . '/admin/devpilot-connected-project-action.php?project_id=' . rawurlencode($projectId) . '&action=' . rawurlencode($action);
        echo json_encode(['success'=>true,'message'=>'Continue in DevPilot Hub to complete this action.','approval_url'=>$projectUrl]);
        exit;
    }
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Unknown connector action.']);
} catch (Throwable $exception) {
    error_log('DevPilot connector action failed: ' . get_class($exception));
    http_response_code(422);
    echo json_encode(['success'=>false,'error'=>$exception instanceof RuntimeException ? $exception->getMessage() : 'The connector action failed.']);
}
