<?php
declare(strict_types=1);

namespace DevPilot\Connector;

interface ConnectorStore
{
    public function createPairing(array $pairing): void;
    public function consumePairing(string $codeHash, int $now): ?array;
    public function saveConnection(array $connection): void;
    public function findConnectionByTokenHash(string $tokenHash): ?array;
    public function findConnectionByProject(string $projectId): ?array;
    public function touchConnection(string $connectionId, int $now): void;
    public function revokeConnection(string $connectionId, int $now): void;
    public function rotateConnection(string $connectionId, array $connection, int $now): void;
}
