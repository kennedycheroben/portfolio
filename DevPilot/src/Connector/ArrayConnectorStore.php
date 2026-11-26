<?php
declare(strict_types=1);

namespace DevPilot\Connector;

/** Deterministic store used by tests and small local integrations. */
final class ArrayConnectorStore implements ConnectorStore
{
    public array $pairings = [];
    public array $connections = [];
    public array $pairingFailures = [];

    public function createPairing(array $pairing): void { $this->pairings[$pairing['code_hash']] = $pairing; }
    public function consumePairing(string $codeHash, int $now): ?array
    {
        $row = $this->pairings[$codeHash] ?? null;
        if ($row === null || $row['used_at'] !== null || $row['expires_at'] <= $now) return null;
        $row['used_at'] = $now;
        $this->pairings[$codeHash] = $row;
        return $row;
    }
    public function saveConnection(array $connection): void { foreach ($this->connections as &$existing) { if ($existing['project_id'] === $connection['project_id'] && (int) ($existing['owner_id'] ?? 0) === (int) ($connection['owner_id'] ?? 0) && $existing['status'] === 'active') { $existing['status'] = 'revoked'; $existing['revoked_at'] = $connection['connected_at']; } } unset($existing); $this->connections[$connection['connection_id']] = $connection; }
    public function findConnectionByTokenHash(string $tokenHash): ?array
    {
        foreach ($this->connections as $row) if ($row['token_hash'] === $tokenHash) return $row;
        return null;
    }
    public function findConnectionByProject(string $projectId): ?array { foreach ($this->connections as $row) if ($row['project_id'] === $projectId && $row['status'] === 'active') return $row; return null; }
    public function touchConnection(string $connectionId, int $now): void { $this->connections[$connectionId]['last_seen_at'] = $now; }
    public function revokeConnection(string $connectionId, int $now): void { $this->connections[$connectionId]['status'] = 'revoked'; $this->connections[$connectionId]['revoked_at'] = $now; }
    public function rotateConnection(string $connectionId, array $connection, int $now): void { $this->revokeConnection($connectionId, $now); $this->saveConnection($connection); }
    public function pairingAllowed(string $key, int $now): bool { $recent = array_filter($this->pairingFailures[$key] ?? [], static fn(int $time): bool => $time > $now - 300); return count($recent) < 10; }
    public function recordPairingFailure(string $key, int $now): void { $this->pairingFailures[$key][] = $now; }
}
