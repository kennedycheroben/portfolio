<?php
declare(strict_types=1);

namespace DevPilot\Admin\Api;

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use DevPilot\Api\DeviceAuth;
use DevPilot\Api\OperationQueue;
use DevPilot\Config;
use DevPilot\Discovery;
use DevPilot\Logger;
use DevPilot\Project;
use DevPilot\Registry;

// Verify admin authentication
requireAdmin();
require_once __DIR__ . '/../includes/devpilot-bootstrap.php';
if (devpilot_initialize_runtime() !== null) {
    devpilot_unavailable(true);
}

// Verify CSRF for state-changing operations
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    verify_csrf();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'] ?? '/admin/api/devpilot';
$parsed = parse_url($uri);
$path = trim(str_replace('/admin/api/devpilot', '', $parsed['path'] ?? ''), '/');

$segments = explode('/', $path);
$routeKey = $segments[0] ?? '';

try {
    $result = handleRequest($method, $segments);
    echo json_encode($result);
} catch (\Throwable $e) {
    Logger::error('API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleRequest(string $method, array $segments): array
{
    return match ($method) {
        'GET' => handleGet($segments),
        'POST' => handlePost($segments),
        'DELETE' => handleDelete($segments),
        default => ['success' => false, 'error' => 'Method not allowed', 'status' => 405]
    };
}

function handleGet(array $segments): array
{
    $routeKey = $segments[0] ?? '';

    return match ($routeKey) {
        'projects' => getProjects(),
        'project' => getProject((int) ($segments[1] ?? 0)),
        'discovery' => getDiscovery((int) ($segments[1] ?? 0)),
        'health' => getHealth(),
        'devices' => getDevices(),
        'operations' => getOperations(),
        'operation' => getOperation((int) ($segments[1] ?? 0)),
        default => ['success' => false, 'error' => 'Not found', 'status' => 404]
    };
}

function handlePost(array $segments): array
{
    $routeKey = $segments[0] ?? '';

    return match ($routeKey) {
        'devices' => registerDevice(),
        'operations' => queueOperation(),
        default => ['success' => false, 'error' => 'Not found', 'status' => 404]
    };
}

function handleDelete(array $segments): array
{
    $routeKey = $segments[0] ?? '';

    if ($routeKey === 'devices' && isset($segments[1])) {
        return deleteDevice($segments[1]);
    }

    return ['success' => false, 'error' => 'Not found', 'status' => 404];
}

function getProjects(): array
{
    $registry = Registry::getInstance();
    $projects = $registry->list();
    $result = [];

    foreach ($projects as $project) {
        $discovery = Discovery::detect($project->path);
        $result[] = [
            'id' => $project->id,
            'name' => $project->name,
            'path' => $project->path,
            'language' => $project->language,
            'framework' => $project->framework,
            'database' => $project->database,
            'environment' => $project->environment,
            'status' => $project->status,
            'adapterStatus' => $project->adapterStatus,
            'hosting' => $project->hosting,
            'hasGit' => $discovery['hasGit'],
            'hasDocker' => $discovery['hasDocker'],
            'primaryLanguage' => $discovery['primaryLanguage'],
            'technologies' => $discovery['technologies'],
            'frameworks' => $discovery['frameworks'],
            'databases' => $discovery['databases'],
            'createdAt' => $project->createdAt,
            'updatedAt' => $project->updatedAt,
        ];
    }

    return ['success' => true, 'projects' => $result];
}

function getProject(int $id): array
{
    if ($id <= 0) {
        return ['success' => false, 'error' => 'Invalid project ID', 'status' => 400];
    }

    $registry = Registry::getInstance();
    $project = $registry->get($id);

    if ($project === null) {
        return ['success' => false, 'error' => 'Project not found', 'status' => 404];
    }

    $discovery = Discovery::detect($project->path);

    return [
        'success' => true,
        'project' => [
            'id' => $project->id,
            'name' => $project->name,
            'path' => $project->path,
            'language' => $project->language,
            'framework' => $project->framework,
            'database' => $project->database,
            'environment' => $project->environment,
            'status' => $project->status,
            'adapterStatus' => $project->adapterStatus,
            'hosting' => $project->hosting,
            'discovery' => $discovery,
            'createdAt' => $project->createdAt,
            'updatedAt' => $project->updatedAt,
        ],
    ];
}

function getDiscovery(int $id): array
{
    if ($id <= 0) {
        return ['success' => false, 'error' => 'Invalid project ID', 'status' => 400];
    }

    $registry = Registry::getInstance();
    $project = $registry->get($id);

    if ($project === null) {
        return ['success' => false, 'error' => 'Project not found', 'status' => 404];
    }

    $discovery = Discovery::detect($project->path);

    return [
        'success' => true,
        'projectId' => $project->id,
        'projectName' => $project->name,
        'discovery' => $discovery,
    ];
}

function getHealth(): array
{
    $registryPath = Config::getRegistryPath();
    $devpilotRoot = Config::getRoot();

    $checks = [
        'registryReadable' => is_readable($registryPath) || !file_exists($registryPath),
        'logsWritable' => is_writable($devpilotRoot . '/logs'),
        'adaptersDir' => is_dir($devpilotRoot . '/adapters'),
        'apiDir' => is_dir($devpilotRoot . '/api'),
        'registryCount' => Registry::getInstance()->count(),
    ];

    $healthy = !in_array(false, $checks, true);

    return [
        'success' => true,
        'healthy' => $healthy,
        'checks' => $checks,
    ];
}

function getDevices(): array
{
    $devices = DeviceAuth::listRegisteredDevices();
    $operations = new OperationQueue();
    $ops = $operations->list();

    $deviceStatus = [];
    foreach ($devices as $deviceId => $deviceInfo) {
        $lastOp = null;
        foreach ($ops as $op) {
            if ($op['deviceId'] === $deviceId) {
                $lastOp = $op;
            }
        }

        $deviceStatus[$deviceId] = [
            'id' => $deviceId,
            'name' => $deviceInfo['name'] ?? 'Unknown',
            'registeredAt' => $deviceInfo['registeredAt'] ?? null,
            'lastSeen' => $deviceInfo['lastSeen'] ?? null,
            'revoked' => $deviceInfo['revoked'] ?? false,
            'online' => isset($deviceInfo['lastSeen']) && (time() - strtotime($deviceInfo['lastSeen'])) < 300,
            'lastOperation' => $lastOp,
        ];
    }

    return ['success' => true, 'devices' => $deviceStatus];
}

function getOperations(): array
{
    $operations = new OperationQueue();
    $ops = $operations->list();

    return ['success' => true, 'operations' => $ops];
}

function getOperation(int $id): array
{
    if ($id <= 0) {
        return ['success' => false, 'error' => 'Invalid operation ID', 'status' => 400];
    }

    $operations = new OperationQueue();
    $op = $operations->get($id);

    if ($op === null) {
        return ['success' => false, 'error' => 'Operation not found', 'status' => 404];
    }

    return ['success' => true, 'operation' => $op];
}

function registerDevice(): array
{
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $name = trim($input['name'] ?? '');
    $publicKey = $input['publicKey'] ?? '';

    if ($name === '' || $publicKey === '') {
        return ['success' => false, 'error' => 'Name and publicKey required', 'status' => 400];
    }

    $result = DeviceAuth::registerDevice($name, $publicKey);
    $deviceId = $result['deviceId'];
    $secret = $result['secret'];
    $token = DeviceAuth::generateToken($deviceId, $secret);

    return [
        'success' => true,
        'deviceId' => $deviceId,
        'secret' => $secret,
        'token' => $token,
        'message' => "Device '{$name}' registered"
    ];
}

function queueOperation(): array
{
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $projectId = (int) ($input['projectId'] ?? 0);
    if ($projectId <= 0) {
        return ['success' => false, 'error' => 'Invalid project ID', 'status' => 400];
    }

    $registry = Registry::getInstance();
    $project = $registry->get($projectId);
    if ($project === null) {
        return ['success' => false, 'error' => 'Project not found', 'status' => 404];
    }

    $operation = $input['operation'] ?? '';
    $allowedOperations = ['check_health', 'run_discovery', 'get_status'];
    if (!in_array($operation, $allowedOperations, true)) {
        return ['success' => false, 'error' => 'Operation not allowed', 'status' => 403];
    }

    $operations = new OperationQueue();
    $operationId = $operations->queue(
        projectId: $projectId,
        operation: $operation,
        parameters: $input['parameters'] ?? [],
        adminId: $_SESSION['user_id'] ?? 0
    );

    return [
        'success' => true,
        'operationId' => $operationId,
        'message' => "Operation queued for project '{$project->name}'"
    ];
}

function deleteDevice(string $deviceId): array
{
    if ($deviceId === '' || $deviceId === 'self') {
        return ['success' => false, 'error' => 'Invalid device ID', 'status' => 400];
    }

    if (!DeviceAuth::revokeDevice($deviceId)) {
        return ['success' => false, 'error' => 'Device not found or already revoked', 'status' => 404];
    }

    return ['success' => true, 'message' => "Device '{$deviceId}' revoked"];
}
