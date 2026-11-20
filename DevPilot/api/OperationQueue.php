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

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath ?? Config::getRoot() . '/registry/' . self::OPERATIONS_FILE;
        $this->load();
    }

    public function queue(int $projectId, string $operation, array $parameters = [], int $adminId = 0): int
    {
        return $this->withLock(function () use ($projectId, $operation, $parameters, $adminId): int {
            $id = $this->nextId++;

            $this->operations[] = [
                'id' => $id,
                'projectId' => $projectId,
                'operation' => $operation,
                'parameters' => $this->sanitizeParameters($parameters),
                'status' => 'pending',
                'deviceId' => null,
                'adminId' => $adminId,
                'approvedAt' => date('c'),
                'claimAt' => null,
                'startedAt' => null,
                'completedAt' => null,
                'result' => null,
                'error' => null,
            ];

            $this->write();
            Logger::info('Operation queued', ['operationId' => $id, 'projectId' => $projectId, 'operation' => $operation]);

            return $id;
        });
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
        return $this->withLock(function () use ($deviceId, $operationId): bool {
            foreach ($this->operations as &$op) {
                if ($op['id'] === $operationId && $op['status'] === 'pending') {
                    $op['deviceId'] = $deviceId;
                    $op['startedAt'] = date('c');
                    $op['status'] = 'processing';
                    unset($op);
                    $this->write();
                    return true;
                }
            }
            unset($op);
            return false;
        });
    }

    public function complete(int $id, array $result): bool
    {
        return $this->withLock(function () use ($id, $result): bool {
            foreach ($this->operations as &$op) {
                if ($op['id'] === $id) {
                    $op['status'] = 'completed';
                    $op['completedAt'] = date('c');
                    $op['result'] = $result;
                    unset($op);
                    $this->write();
                    return true;
                }
            }
            unset($op);
            return false;
        });
    }

    public function fail(int $id, string $error): bool
    {
        return $this->withLock(function () use ($id, $error): bool {
            foreach ($this->operations as &$op) {
                if ($op['id'] === $id) {
                    $op['status'] = 'failed';
                    $op['error'] = $error;
                    unset($op);
                    $this->write();
                    return true;
                }
            }
            unset($op);
            return false;
        });
    }

    public function cancel(int $id): bool
    {
        return $this->withLock(function () use ($id): bool {
            foreach ($this->operations as &$op) {
                if ($op['id'] === $id && in_array($op['status'], ['pending', 'processing'], true)) {
                    $op['status'] = 'cancelled';
                    $op['error'] = 'CANCELLED';
                    unset($op);
                    $this->write();
                    return true;
                }
            }
            unset($op);
            return false;
        });
    }

    public function cleanExpired(int $maxAgeSeconds = 86400): int
    {
        return $this->withLock(function () use ($maxAgeSeconds): int {
            $cutoff = time() - $maxAgeSeconds;
            $initialCount = count($this->operations);

            $this->operations = array_values(array_filter($this->operations, function($op) use ($cutoff) {
                $completedAt = strtotime($op['completedAt'] ?? '');
                $approvedAt = strtotime($op['approvedAt'] ?? '');

                return ($completedAt === false || $completedAt > $cutoff)
                    || ($approvedAt !== false && $approvedAt > $cutoff);
            }));

            $removedCount = $initialCount - count($this->operations);
            if ($removedCount > 0) {
                $this->write();
            }

            return $removedCount;
        });
    }

    private function withLock(callable $operation): mixed
    {
        $this->acquireLock();
        try {
            $this->load();
            return $operation();
        } finally {
            $this->releaseLock();
        }
    }

    private function load(): void
    {
        clearstatcache(true, $this->filePath);
        if (!file_exists($this->filePath)) {
            $this->operations = [];
            $this->nextId = 1;
            return;
        }

        if (!is_file($this->filePath) || !is_readable($this->filePath)) {
            throw new \RuntimeException('Operation queue file is not readable');
        }

        $content = @file_get_contents($this->filePath);
        if ($content === false) {
            throw new \RuntimeException('Operation queue file could not be read');
        }

        try {
            $document = json_decode($content, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException('Operation queue contains invalid JSON data', 0, $error);
        }

        if (!is_object($document)
            || !property_exists($document, 'operations')
            || !is_array($document->operations)
            || (property_exists($document, 'next_id') && (!is_int($document->next_id) || $document->next_id < 1))) {
            throw new \RuntimeException('Operation queue contains invalid JSON data');
        }

        foreach ($document->operations as $op) {
            if (!is_object($op)
                || !isset($op->id, $op->projectId, $op->operation, $op->status, $op->parameters)
                || !is_int($op->id)
                || $op->id < 1
                || !is_int($op->projectId)
                || !is_string($op->operation)
                || $op->operation === ''
                || !is_string($op->status)
                || (!is_array($op->parameters) && !is_object($op->parameters))) {
                throw new \RuntimeException('Operation queue contains an invalid operation record');
            }
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->operations = $data['operations'];
        $this->nextId = max(1, $data['next_id'] ?? 1);
        foreach ($this->operations as $op) {
            if ($op['id'] >= $this->nextId) {
                $this->nextId = $op['id'] + 1;
            }
        }
    }

    private function acquireLock(): void
    {
        if (is_resource($this->lockHandle)) {
            throw new \LogicException('Operation queue lock is already held by this instance');
        }

        $dir = dirname($this->filePath);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Failed to create operations directory');
        }

        $lockPath = $this->filePath . '.lock';
        $handle = fopen($lockPath, 'c');
        if ($handle === false) {
            throw new \RuntimeException('Failed to acquire operation queue lock');
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new \RuntimeException('Failed to acquire exclusive lock on operation queue');
        }

        $this->lockHandle = $handle;
        @chmod($lockPath, 0600);
    }

    private function releaseLock(): void
    {
        if (isset($this->lockHandle) && is_resource($this->lockHandle)) {
            $handle = $this->lockHandle;
            $this->lockHandle = null;
            try {
                flock($handle, LOCK_UN);
            } finally {
                fclose($handle);
            }
        }
    }

    private function write(): void
    {
        if (!is_resource($this->lockHandle)) {
            throw new \LogicException('Operation queue writes require the queue lock');
        }

        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true)) {
                throw new \RuntimeException('Failed to create operations directory');
            }
        }

        $tempFile = null;
        try {
            $data = [
                'exported_at' => date('c'),
                'version' => '1.0',
                'count' => count($this->operations),
                'next_id' => $this->nextId,
                'operations' => array_values($this->operations),
            ];

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new \RuntimeException('Failed to encode operations data');
            }

            $tempFile = tempnam($dir, '.operations-');
            if ($tempFile === false) {
                throw new \RuntimeException('Failed to create temporary operations file');
            }
            if (realpath(dirname($tempFile)) !== realpath($dir)) {
                throw new \RuntimeException('Temporary operations file was created outside the registry directory');
            }
            if (file_put_contents($tempFile, $json, LOCK_EX) !== strlen($json)) {
                throw new \RuntimeException('Failed to write operations file');
            }

            chmod($tempFile, 0600);
            if (!rename($tempFile, $this->filePath)) {
                throw new \RuntimeException('Failed to atomically replace operations file');
            }
            $tempFile = null;
        } finally {
            if ($tempFile !== null && is_file($tempFile)) {
                @unlink($tempFile);
            }
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
