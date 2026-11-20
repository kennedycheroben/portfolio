<?php
declare(strict_types=1);

namespace DevPilot\Api;

use DevPilot\Config;
use DevPilot\Discovery;
use DevPilot\Logger;
use DevPilot\Project;
use DevPilot\Registry;
use OutOfBoundsException;

final class Endpoints
{
    private static ?Endpoints $instance = null;
    private Registry $registry;
    private OperationQueue $operations;
    private DeviceAuth $deviceAuth;

    private function __construct()
    {
        $this->registry = Registry::getInstance();
        $this->operations = new OperationQueue();
        $this->deviceAuth = new DeviceAuth();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function handleRequest(string $method, string $path, ?string $authToken = null): mixed
    {
        $path = trim($path, '/');
        $segments = explode('/', $path);

        Logger::info('API request', [
            'method' => $method,
            'path' => $path,
            'auth' => $authToken ? 'provided' : 'none'
        ]);

        return match ($method) {
            'GET' => $this->handleGet($segments, $authToken),
            'POST' => $this->handlePost($segments, $authToken),
            'DELETE' => $this->handleDelete($segments, $authToken),
            default => $this->jsonResponse(['error' => 'Method not allowed'], 405)
        };
    }

    private function handleGet(array $segments, ?string $authToken): mixed
    {
        return match ($segments[0] ?? '') {
            'projects' => $this->getProjects($segments, $authToken),
            'project' => $this->getProject($segments, $authToken),
            'discovery' => $this->getDiscovery($segments, $authToken),
            'health' => $this->getHealth($authToken),
            'devices' => $this->getDevices($authToken),
            'operations' => $this->getOperations($segments, $authToken),
            'operation' => $this->getOperation($segments, $authToken),
            default => $this->jsonResponse(['error' => 'Not found'], 404)
        };
    }

    private function handlePost(array $segments, ?string $authToken): mixed
    {
        return match ($segments[0] ?? '') {
            'operations' => $this->queueOperation($segments, $authToken),
            'devices' => $this->registerDevice($segments, $authToken),
            'operations' . '/' . ($segments[1] ?? '') . '/execute' => $this->executeOperation($segments, $authToken),
            default => $this->jsonResponse(['error' => 'Not found'], 404)
        };
    }

    private function handleDelete(array $segments, ?string $authToken): mixed
    {
        return match ($segments[0] ?? '') {
            'devices' => $this->deleteDevice($segments, $authToken),
            default => $this->jsonResponse(['error' => 'Not found'], 404)
        };
    }

    private function getProjects(array $segments, ?string $authToken): mixed
    {
        $device = $this->authenticateDevice($authToken);
        if ($device === null) {
            return $this->jsonResponse(['error' => 'Admin authentication required'], 401);
        }

        $projects = $this->registry->list();
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

        return $this->jsonResponse($result);
    }

    private function getProject(array $segments, ?string $authToken): mixed
    {
        $device = $this->authenticateDevice($authToken);
        if ($device === null) {
            return $this->jsonResponse(['error' => 'Admin authentication required'], 401);
        }

        $id = (int) ($segments[1] ?? 0);
        if ($id <= 0) {
            return $this->jsonResponse(['error' => 'Invalid project ID'], 400);
        }

        $project = $this->registry->get($id);
        if ($project === null) {
            return $this->jsonResponse(['error' => 'Project not found'], 404);
        }

        $discovery = Discovery::detect($project->path);

        return $this->jsonResponse([
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
        ]);
    }

    private function getDiscovery(array $segments, ?string $authToken): mixed
    {
        $device = $this->authenticateDevice($authToken);
        if ($device === null) {
            return $this->jsonResponse(['error' => 'Admin authentication required'], 401);
        }

        $id = (int) ($segments[1] ?? 0);
        if ($id <= 0) {
            return $this->jsonResponse(['error' => 'Invalid project ID'], 400);
        }

        $project = $this->registry->get($id);
        if ($project === null) {
            return $this->jsonResponse(['error' => 'Project not found'], 404);
        }

        $discovery = Discovery::detect($project->path);

        return $this->jsonResponse([
            'projectId' => $project->id,
            'projectName' => $project->name,
            'discovery' => $discovery,
        ]);
    }

    private function getHealth(?string $authToken): mixed
    {
        $device = $this->authenticateDevice($authToken);
        if ($device === null) {
            return $this->jsonResponse(['error' => 'Authentication required'], 401);
        }

        $registryPath = Config::getRegistryPath();
        $devpilotRoot = Config::getRoot();

        $checks = [
            'registryReadable' => is_readable($registryPath) || !file_exists($registryPath),
            'logsWritable' => is_writable($devpilotRoot . '/logs'),
            'adaptersDir' => is_dir($devpilotRoot . '/adapters'),
            'apiDir' => is_dir($devpilotRoot . '/api'),
            'registryCount' => $this->registry->count(),
        ];

        return $this->jsonResponse([
            'healthy' => empty(array_filter($checks, fn($v) => $v === false)),
            'checks' => $checks,
        ]);
    }

    private function getDevices(?string $authToken): mixed
    {
        $device = $this->authenticateDevice($authToken);
        if ($device === null) {
            return $this->jsonResponse(['error' => 'Admin authentication required'], 401);
        }

        $devices = DeviceAuth::listRegisteredDevices();
        $operations = $this->operations->list();

        $deviceStatus = [];
        foreach ($devices as $deviceId => $deviceInfo) {
            $lastOp = null;
            foreach ($operations as $op) {
                if ($op['deviceId'] === $deviceId) {
                    $lastOp = $op;
                }
            }

            $deviceStatus[$deviceId] = [
                'id' => $deviceId,
                'name' => $deviceInfo['name'] ?? 'Unknown',
                'registeredAt' => $deviceInfo['registeredAt'] ?? null,
                'lastSeen' => $deviceInfo['lastSeen'] ?? null,
                'online' => isset($deviceInfo['lastSeen']) && (time() - strtotime($deviceInfo['lastSeen'])) < 300,
                'lastOperation' => $lastOp,
            ];
        }

        return $this->jsonResponse($deviceStatus);
    }

    private function getOperations(array $segments, ?string $authToken): mixed
    {
        $device = $this->authenticateDevice($authToken);
        if ($device === null) {
            return $this->jsonResponse(['error' => 'Admin authentication required'], 401);
        }

        $operations = $this->operations->list();
        return $this->jsonResponse($operations);
    }

    private function getOperation(array $segments, ?string $authToken): mixed
    {
        $device = $this->authenticateDevice($authToken);
        if ($device === null) {
            return $this->jsonResponse(['error' => 'Admin authentication required'], 401);
        }

        $id = (int) ($segments[1] ?? 0);
        if ($id <= 0) {
            return $this->jsonResponse(['error' => 'Invalid operation ID'], 400);
        }

        $operation = $this->operations->get($id);
        if ($operation === null) {
            return $this->jsonResponse(['error' => 'Operation not found'], 404);
        }

        return $this->jsonResponse($operation);
    }

    private function queueOperation(array $segments, ?string $authToken): mixed
    {
        $device = $this->authenticateDevice($authToken);
        if ($device === null) {
            return $this->jsonResponse(['error' => 'Admin authentication required'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $projectId = (int) ($input['projectId'] ?? 0);
        if ($projectId <= 0) {
            return $this->jsonResponse(['error' => 'Invalid project ID'], 400);
        }

        $project = $this->registry->get($projectId);
        if ($project === null) {
            return $this->jsonResponse(['error' => 'Project not found'], 404);
        }

        $operation = $input['operation'] ?? '';
        $allowedOperations = ['check_health', 'run_discovery', 'get_status'];
        if (!in_array($operation, $allowedOperations, true)) {
            return $this->jsonResponse(['error' => 'Operation not allowed'], 403);
        }

        $operationId = $this->operations->queue(
            projectId: $projectId,
            operation: $operation,
            parameters: $input['parameters'] ?? [],
            adminId: $_SESSION['user_id'] ?? 0
        );

        return $this->jsonResponse([
            'success' => true,
            'operationId' => $operationId,
            'message' => "Operation queued for project '{$project->name}'"
        ], 201);
    }

    private function registerDevice(array $segments, ?string $authToken): mixed
    {
        $device = $this->authenticateDevice($authToken);
        if ($device === null) {
            return $this->jsonResponse(['error' => 'Admin authentication required'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $name = trim($input['name'] ?? '');
        $publicKey = $input['publicKey'] ?? '';

        if ($name === '' || $publicKey === '') {
            return $this->jsonResponse(['error' => 'Name and publicKey required'], 400);
        }

        $deviceId = DeviceAuth::registerDevice($name, $publicKey);

        return $this->jsonResponse([
            'success' => true,
            'deviceId' => $deviceId,
            'message' => "Device '{$name}' registered"
        ], 201);
    }

    private function deleteDevice(array $segments, ?string $authToken): mixed
    {
        $device = $this->authenticateDevice($authToken);
        if ($device === null) {
            return $this->jsonResponse(['error' => 'Admin authentication required'], 401);
        }

        $deviceId = $segments[1] ?? '';
        if ($deviceId === '' || $deviceId === 'self') {
            return $this->jsonResponse(['error' => 'Invalid device ID'], 400);
        }

        if (!DeviceAuth::revokeDevice($deviceId)) {
            return $this->jsonResponse(['error' => 'Device not found or already revoked'], 404);
        }

        return $this->jsonResponse([
            'success' => true,
            'message' => "Device '{$deviceId}' revoked"
        ]);
    }

    private function executeOperation(array $segments, ?string $authToken): mixed
    {
        $device = $this->authenticateDevice($authToken);
        if ($device === null) {
            return $this->jsonResponse(['error' => 'Device authentication required'], 401);
        }

        $operationId = (int) ($segments[1] ?? 0);
        if ($operationId <= 0) {
            return $this->jsonResponse(['error' => 'Invalid operation ID'], 400);
        }

        $operation = $this->operations->getForDevice($deviceId, $operationId);
        if ($operation === null) {
            return $this->jsonResponse(['error' => 'Operation not found or not authorized'], 404);
        }

        if ($operation['status'] !== 'pending') {
            return $this->jsonResponse(['error' => 'Operation not pending'], 403);
        }

        $result = $this->executeOperationInternal($operation);

        return $this->jsonResponse([
            'success' => true,
            'operationId' => $operationId,
            'result' => $result
        ]);
    }

    private function authenticateDevice(?string $authToken): ?array
    {
        if ($authToken === null) {
            return null;
        }

        return DeviceAuth::authenticate($authToken);
    }

    private function executeOperationInternal(array $operation): array
    {
        $projectId = $operation['projectId'];
        $project = $this->registry->get($projectId);
        if ($project === null) {
            return ['error' => 'Project not found', 'success' => false];
        }

        $operationName = $operation['operation'];
        $result = ['success' => false];

        try {
            switch ($operationName) {
                case 'check_health':
                    $result = $this->checkProjectHealth($project);
                    break;
                case 'run_discovery':
                    $result = $this->runProjectDiscovery($project);
                    break;
                case 'get_status':
                    $result = $this->getProjectStatus($project);
                    break;
                default:
                    $result = ['error' => 'Unknown operation', 'success' => false];
            }
            $result['success'] = true;
        } catch (\Throwable $e) {
            Logger::error('Operation execution failed', ['operation' => $operationName, 'error' => $e->getMessage()]);
            $result = ['error' => 'Execution failed', 'details' => $e->getMessage(), 'success' => false];
        }

        $this->operations->complete($operation['id'], $result);

        return $result;
    }

    private function checkProjectHealth(Project $project): array
    {
        $discovery = Discovery::detect($project->path);

        $issues = [];
        $warnings = [];

        if (!is_readable($project->path)) {
            $issues[] = "Project path not readable";
        }

        if (!$discovery['hasGit']) {
            $warnings[] = "Project not a Git repository";
        }

        if ($project->database && $project->database !== 'none') {
            if (!in_array($project->database, ['mysql', 'postgresql', 'sqlite'], true)) {
                $warnings[] = "Database '{$project->database}' may not be supported";
            }
        }

        return [
            'projectId' => $project->id,
            'name' => $project->name,
            'healthy' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'language' => $discovery['primaryLanguage'],
            'frameworks' => $discovery['frameworks'],
            'databases' => $discovery['databases'],
        ];
    }

    private function runProjectDiscovery(Project $project): array
    {
        $discovery = Discovery::detect($project->path);

        return [
            'projectId' => $project->id,
            'name' => $project->name,
            'discovery' => $discovery,
        ];
    }

    private function getProjectStatus(Project $project): array
    {
        return [
            'projectId' => $project->id,
            'name' => $project->name,
            'path' => $project->path,
            'language' => $project->language,
            'framework' => $project->framework,
            'database' => $project->database,
            'environment' => $project->environment,
            'status' => $project->status,
            'adapterStatus' => $project->adapterStatus,
            'hosting' => $project->hosting,
            'hasGit' => is_dir($project->path . '/.git'),
            'pathExists' => is_dir($project->path),
        ];
    }

    private function jsonResponse(mixed $data, int $statusCode = 200): array
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        return [
            'status' => $statusCode,
            'data' => $data
        ];
    }
}