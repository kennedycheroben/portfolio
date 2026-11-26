<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../DevPilot/autoload.php';

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
$projects = WebsiteServices::connectedProjects($pdo);
$http = new ConnectorHttpClient();

try {
    $appEnvironment = (string) getenv('APP_ENV');
    $connectorPermitted = in_array($appEnvironment, ['development','local','staging'], true)
        || ($appEnvironment === 'production' && getenv('DEVPILOT_CONNECTOR_SECURITY_VERIFIED') === '1');
    if (!$connectorPermitted && $action !== 'disconnect') {
        throw new RuntimeException('The connector is disabled until its environment is explicitly approved.');
    }
    $origin = ConnectorPolicy::origin($identityOrigin);
    if ($action === 'connect') {
        $pairing = $service->beginPairing($projectId, $projectName, $origin, [ConnectorPolicy::INSPECT], time(), $ownerId);
        $authorization = $http->post($origin, '/api/devpilot-connector.php?action=authorize', null, ['pairing_code'=>$pairing['pairing_code']]);
        $connection = $authorization['connection'] ?? null;
        try {
            if (!is_array($connection) || !is_string($connection['access_token'] ?? null)
                || !hash_equals($projectId, (string) ($connection['project_id'] ?? ''))
                || !hash_equals($origin, (string) ($connection['origin'] ?? ''))
                || !in_array(ConnectorPolicy::INSPECT, (array) ($connection['scopes'] ?? []), true)) {
                throw new RuntimeException('Project authorization response could not be verified.');
            }
            $result = $http->post($origin, '/api/devpilot-connector.php?action=inspect', $connection['access_token']);
            $inspection = $result['inspection'] ?? null;
            if (!is_array($inspection) || !hash_equals($projectId, (string) ($inspection['project_id'] ?? '')) || !hash_equals($origin, (string) ($inspection['origin'] ?? ''))) throw new RuntimeException('Project inspection response could not be verified.');
            $projects->save($ownerId, $connection, $connection['access_token'], $inspection, time());
        } catch (Throwable $exception) {
            if (is_array($connection) && is_string($connection['access_token'] ?? null)) {
                try { $http->post($origin, '/api/devpilot-connector.php?action=revoke', $connection['access_token']); } catch (Throwable) {}
            }
            throw $exception;
        }
        echo json_encode(['success'=>true,'message'=>'DevPilot connected and inspected the project.','connection'=>$projects->status($ownerId,$projectId)], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($action === 'refresh') {
        try {
            $token = $projects->token($ownerId, $projectId);
            $result = $http->post($origin, '/api/devpilot-connector.php?action=inspect', $token);
            $inspection = $result['inspection'] ?? null;
            if (!is_array($inspection) || !hash_equals($projectId, (string) ($inspection['project_id'] ?? '')) || !hash_equals($origin, (string) ($inspection['origin'] ?? ''))) throw new RuntimeException('Project inspection response could not be verified.');
            $projects->updateInspection($ownerId, $projectId, $inspection, time());
        } catch (Throwable $exception) {
            $projects->recordInspectionFailure($ownerId, $projectId, time());
            throw $exception;
        }
        echo json_encode(['success'=>true,'message'=>'Project inspection refreshed.','connection'=>$projects->status($ownerId,$projectId)], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($action === 'disconnect') {
        $token = $projects->token($ownerId, $projectId);
        $http->post($origin, '/api/devpilot-connector.php?action=revoke', $token);
        $projects->disconnect($ownerId, $projectId, time());
        echo json_encode(['success'=>true,'message'=>'DevPilot authorization was revoked.']);
        exit;
    }
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Unknown connector action.']);
} catch (Throwable $exception) {
    error_log('DevPilot connector action failed: ' . get_class($exception));
    http_response_code(422);
    echo json_encode(['success'=>false,'error'=>$exception instanceof RuntimeException ? $exception->getMessage() : 'The connector action failed.']);
}
