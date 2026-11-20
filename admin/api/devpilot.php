<?php
declare(strict_types=1);

namespace DevPilot\Admin\Api;

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use DevPilot\Api\DeviceAuth;
use DevPilot\Api\Endpoints;
use DevPilot\Api\OperationQueue;
use DevPilot\Config;
use DevPilot\Discovery;
use DevPilot\Logger;
use DevPilot\Project;
use DevPilot\Registry;

// Verify admin authentication
requireAdmin();

// Verify CSRF for state-changing operations
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    verify_csrf();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PARSE_URL_PATH);
$path = trim(str_replace('/admin/api/devpilot', '', $path), '/');

$routes = [
    'GET' => [
        'projects' => 'listProjects',
        'health' => 'getHealth',
        'devices' => 'getDevices',
        'operations' => 'getOperations',
    ],
    'POST' => [
        'devices' => 'registerDevice',
        'operations' => 'queueOperation',
    ],
    'DELETE' => [
        'devices' => 'deleteDevice',
    ],
];

$segments = explode('/', trim($path, '/'));
$routeKey = $segments[0] ?? '';

$handler = null;

if (isset($routes[$method][$routeKey])) {
    $handler = [$this, $routes[$method][$routeKey]];
} elseif ($method === 'GET' && preg_match('~^project/(\d+)$~', $path, $matches)) {
    $handler = function() use ($matches) {
        return getProject((int) $matches[1]);
    };
} elseif ($method === 'GET' && preg_match('~^discovery/(\d+)$~', $path, $matches)) {
    $handler = function() use ($matches) {
        return getDiscovery((int) $matches[1]);
    };
} elseif ($method === 'GET' && preg_match('~^operation/(\d+)$~', $path, $matches)) {
    $handler = function() use ($matches) {
        return getOperation((int) $matches[1]);
    };
} elseif ($method === 'POST' && preg_match('~^operations/(\d+)/execute$~', $path, $matches)) {
    $handler = function() use ($matches) {
        return executeOperation((int) $matches[1]);
    };
} elseif ($method === 'DELETE' && preg_match('~^devices/(.+)$~', $path, $matches)) {
    $handler = function() use ($matches) {
        return deleteDevice($matches[1]);
    };
}

if ($handler === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

try {
    $result = $handler();
    echo json_encode($result);
} catch (\Throwable $e) {
    Logger::error('API error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

// Handler functions
function listProjects(): array
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
    $registry = Registry::getInstance();
    $project = $registry->get($id);

    if ($project === null) {
        return ['error' => 'Project not found', 'success' => false];
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
    $registry = Registry::getInstance();
    $project = $registry->get($id);

    if ($project === null) {
        return ['error' => 'Project not found', 'success' => false];
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
    $operations = new OperationQueue();
    $op = $operations->get($id);

    if ($op === null) {
        return ['error' => 'Operation not found', 'success' => false];
    }

    return ['success' => true, 'operation' => $op];
}

function registerDevice(): array
{
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $name = trim($input['name'] ?? '');
    $publicKey = $input['publicKey'] ?? '';

    if ($name === '' || $publicKey === '') {
        return ['error' => 'Name and publicKey required', 'success' => false];
    }

    $deviceId = DeviceAuth::registerDevice($name, $publicKey);

    return [
        'success' => true,
        'deviceId' => $deviceId,
        'message' => "Device '{$name}' registered",
        'token' => DeviceAuth::generateToken($deviceId),
    ];
}

function queueOperation(): array
{
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $projectId = (int) ($input['projectId'] ?? 0);
    if ($projectId <= 0) {
        return ['error' => 'Invalid project ID', 'success' => false];
    }

    $registry = Registry::getInstance();
    $project = $registry->get($projectId);
    if ($project === null) {
        return ['error' => 'Project not found', 'success' => false];
    }

    $operation = $input['operation'] ?? '';
    $allowedOperations = ['check_health', 'run_discovery', 'get_status'];
    if (!in_array($operation, $allowedOperations, true)) {
        return ['error' => 'Operation not allowed', 'success' => false];
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
        return ['error' => 'Invalid device ID', 'success' => false];
    }

    if (!DeviceAuth::revokeDevice($deviceId)) {
        return ['error' => 'Device not found or already revoked', 'success' => false];
    }

    return ['success' => true, 'message' => "Device '{$deviceId}' revoked"];
}

function executeOperation(int $operationId): array
{
    $operations = new OperationQueue();
    $op = $operations->get($operationId);

    if ($op === null) {
        return ['error' => 'Operation not found', 'success' => false];
    }

    if ($op['status'] !== 'pending') {
        return ['error' => 'Operation not pending', 'success' => false];
    }

    $registry = Registry::getInstance();
    $project = $registry->get($op['projectId']);
    if ($project === null) {
        return ['error' => 'Project not found', 'success' => false];
    }

    $result = ['success' => false];

    switch ($op['operation']) {
        case 'check_health':
            $result = [
                'projectId' => $project->id,
                'name' => $project->name,
                'healthy' => true,
                'issues' => [],
                'warnings' => [],
                'language' => $op['parameters']['language'] ?? $project->language,
            ];
            break;
        case 'run_discovery':
            $discovery = Discovery::detect($project->path);
            $result = [
                'projectId' => $project->id,
                'name' => $project->name,
                'discovery' => $discovery,
            ];
            break;
        case 'get_status':
            $result = [
                'projectId' => $project->id,
                'name' => $project->name,
                'path' => $project->path,
                'language' => $project->language,
                'framework' => $project->framework,
                'status' => $project->status,
            ];
            break;
        default:
            $result = ['error' => 'Unknown operation', 'success' => false];
    }

    $operations->complete($operationId, $result);

    return ['success' => true, 'result' => $result];
}