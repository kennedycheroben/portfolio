<?php
declare(strict_types=1);

namespace DevPilot\Api;

use DevPilot\Config;
use DevPilot\Logger;

final class OperationQueue
{
    private const OPERATIONS_FILE = 'operations.json';
    private array $operations = [];
    private int $nextId = 1;
    private string $filePath;
    private $lockHandle = null;

    public function __construct()
    {
        $this->filePath = Config::getRoot() . '/registry/' . self::OPERATIONS_FILE;
        $this->load();
    }

    public function queue(int $projectId, string $operation, array $parameters = [], int $adminId = 0): int
    {
        $this->acquireLock();

        try {
            $this->load();
            $id = $this->nextId++;

            $this->operations[] = [
                'id' => $id,
                'projectId' => $projectId,
                'operation' => $operation,
                'parameters' => $this->sanitizeParameters($parameters),
                'parametersOriginal' => $parameters,
                'status' => 'pending',
                'deviceId' => null,
                'adminId' => $adminId,
                'approvedAt' => date('c'),
                'startedAt' => null,
                'completedAt' => null,
                'result' => null,
                'error' => null,
            ];

            $this->write();
            Logger::info('Operation queued', ['operationId' => $id, 'projectId' => $projectId, 'operation' => $operation]);

            return $id;
        } finally {
            $this->releaseLock();
        }
    }

    public function get(int $id): ?array
    {
        foreach ($this->operations as $op) {
            if ($op['id'] === $id) {
                return $op;
            }
        }
        return null;
    }

    public function getForDevice(string $deviceId, int $id): ?array
    {
        foreach ($this->operations as $op) {
            if ($op['id'] === $id && $op['deviceId'] === $deviceId) {
                return $op;
            }
        }
        return null;
    }

    public function list(?string $status = null, ?string $deviceId = null, ?int $projectId = null): array
    {
        $this->load();
        $result = $this->operations;

        if ($status !== null) {
            $result = array_filter($result, fn($op) => $op['status'] === $status);
        }

        if ($deviceId !== null) {
            $result = array_filter($result, fn($op) => $op['deviceId'] === $deviceId);
        }

        if ($projectId !== null) {
            $result = array_filter($result, fn($op) => $op['projectId'] === $projectId);
        }

        return array_values($result);
    }

    public function assignToDevice(string $deviceId, int $operationId): bool
    {
        foreach ($this->operations as &$op) {
            if ($op['id'] === $operationId && $op['status'] === 'pending') {
                $op['deviceId'] = $deviceId;
                $op['startedAt'] = date('c');
                $op['status'] = 'processing';
                $this->write();
                return true;
            }
        }
        return false;
    }

    public function complete(int $id, array $result): bool
    {
        foreach ($this->operations as &$op) {
            if ($op['id'] === $id) {
                $op['status'] = 'completed';
                $op['completedAt'] = date('c');
                $op['result'] = $result;
                $this->write();
                return true;
            }
        }
        return false;
    }

    public function fail(int $id, string $error): bool
    {
        foreach ($this->operations as &$op) {
            if ($op['id'] === $id) {
                $op['status'] = 'failed';
                $op['error'] = $error;
                $this->write();
                return true;
            }
        }
        return false;
    }

    public function cancel(int $id): bool
    {
        foreach ($this->operations as &$op) {
            if ($op['id'] === $id && in_array($op['status'], ['pending', 'processing'], true)) {
                $op['status'] = 'cancelled';
                $op['error'] = 'CANCELLED';
                $this->write();
                return true;
            }
        }
        return false;
    }

    public function cleanExpired(int $maxAgeSeconds = 86400): int
    {
        $cutoff = time() - $maxAgeSeconds;
        $initialCount = count($this->operations);

        $this->operations = array_filter($this->operations, function($op) use ($cutoff) {
            $completedAt = strtotime($op['completedAt'] ?? '');
            $approvedAt = strtotime($op['approvedAt'] ?? '');

            return ($completedAt === false || $completedAt > $cutoff)
                || ($approvedAt !== false && $approvedAt > $cutoff);
        });

        if (count($this->operations) < $initialCount) {
            $this->write();
        }

        return $initialCount - count($this->operations);
    }

    private function load(): void
    {
        if (!is_readable($this->filePath)) {
            $this->operations = [];
            $this->nextId = 1;
            return;
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            $this->operations = [];
            $this->nextId = 1;
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            $this->operations = [];
            $this->nextId = 1;
            return;
        }

        $this->operations = $data['operations'] ?? [];
        foreach ($this->operations as $op) {
            if (isset($op['id']) && $op['id'] >= $this->nextId) {
                $this->nextId = $op['id'] + 1;
            }
        }
    }

    private function acquireLock(): void
    {
        $lockPath = $this->filePath . '.lock';
        $handle = fopen($lockPath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Failed to acquire operation queue lock');
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new \RuntimeException('Failed to acquire exclusive lock on operation queue');
        }

        $this->lockHandle = $handle;
    }

    private function releaseLock(): void
    {
        if (isset($this->lockHandle) && is_resource($this->lockHandle)) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    private function write(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true)) {
                throw new \RuntimeException('Failed to create operations directory');
            }
        }

        $lockPath = dirname($this->filePath) . '/' . basename($this->filePath) . '.lock';

        $handle = fopen($lockPath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Failed to acquire write lock');
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new \RuntimeException('Failed to acquire exclusive lock');
        }

        try {
            $data = [
                'exported_at' => date('c'),
                'version' => '1.0',
                'count' => count($this->operations),
                'operations' => array_values($this->operations),
            ];

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new \RuntimeException('Failed to encode operations data');
            }

            $tempFile = $this->filePath . '.tmp.' . uniqid('', true);
            if (file_put_contents($tempFile, $json) === false) {
                throw new \RuntimeException('Failed to write operations file');
            }

            chmod($tempFile, 0600);
            if (!rename($tempFile, $this->filePath)) {
                unlink($tempFile);
                throw new \RuntimeException('Failed to atomically replace operations file');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function sanitizeParameters(array $parameters): array
    {
        $sensitiveKeys = ['password', 'api_key', 'secret', 'token', 'key', 'credential', 'auth'];
        $result = [];

        foreach ($parameters as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitive) {
                if (strpos($lowerKey, $sensitive) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $result[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $result[$key] = $this->sanitizeParameters($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}