<?php
declare(strict_types=1);

namespace DevPilot\Connector;

use PDO;

final class PdoConnectorStore implements ConnectorStore
{
    private readonly bool $mysql;

    public function __construct(private readonly PDO $pdo)
    {
        $this->mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    public function createPairing(array $pairing): void
    {
        $expiry = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
        $stmt = $this->pdo->prepare("INSERT INTO devpilot_connector_pairings (code_hash, project_id, project_name, origin, scopes_json, expires_at, owner_id) VALUES (?, ?, ?, ?, ?, $expiry, ?)");
        $stmt->execute([$pairing['code_hash'],$pairing['project_id'],$pairing['project_name'],$pairing['origin'],json_encode($pairing['scopes'], JSON_THROW_ON_ERROR),$pairing['expires_at'],$pairing['owner_id'] ?? 0]);
    }

    public function consumePairing(string $codeHash, int $now): ?array
    {
        $started = $this->beginWrite();
        try {
            $predicate = $this->mysql ? 'expires_at > FROM_UNIXTIME(?)' : 'expires_at > ?';
            $lockClause = $this->mysql ? ' FOR UPDATE' : '';
            $stmt = $this->pdo->prepare("SELECT * FROM devpilot_connector_pairings WHERE code_hash = ? AND used_at IS NULL AND $predicate$lockClause");
            $stmt->execute([$codeHash,$now]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) { $this->rollbackWrite($started); return null; }
            $usedAt = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
            $update = $this->pdo->prepare("UPDATE devpilot_connector_pairings SET used_at = $usedAt WHERE id = ? AND used_at IS NULL");
            $update->execute([$now,$row['id']]);
            if ($update->rowCount() !== 1) { $this->rollbackWrite($started); return null; }
            $this->commitWrite($started);
            $row['scopes'] = json_decode((string) $row['scopes_json'], true) ?: [];
            return $row;
        } catch (\Throwable $exception) {
            $this->rollbackWrite($started);
            throw $exception;
        }
    }

    public function saveConnection(array $connection): void
    {
        $started = $this->beginWrite();
        try {
            $owner = (int) ($connection['owner_id'] ?? 0);
            $lockSql = 'SELECT id FROM devpilot_connector_connections WHERE owner_id=? AND project_id=?' . ($this->mysql ? ' FOR UPDATE' : '');
            $lock = $this->pdo->prepare($lockSql);
            $lock->execute([$owner,$connection['project_id']]);
            $lock->fetchAll(PDO::FETCH_COLUMN);
            $revokeTime = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
            $revoke = $this->pdo->prepare("UPDATE devpilot_connector_connections SET status='revoked', revoked_at=$revokeTime WHERE project_id=? AND owner_id=? AND status='active'");
            $revoke->execute([$connection['connected_at'],$connection['project_id'],$owner]);
            $stamp = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
            $insert = $this->pdo->prepare("INSERT INTO devpilot_connector_connections (connection_id, project_id, project_name, origin, scopes_json, token_hash, status, connected_at, last_seen_at, expires_at, owner_id) VALUES (?, ?, ?, ?, ?, ?, ?, $stamp, $stamp, $stamp, ?)");
            $insert->execute([$connection['connection_id'],$connection['project_id'],$connection['project_name'],$connection['origin'],json_encode($connection['scopes'], JSON_THROW_ON_ERROR),$connection['token_hash'],$connection['status'],$connection['connected_at'],$connection['last_seen_at'],$connection['expires_at'],$owner]);
            $this->commitWrite($started);
        } catch (\Throwable $exception) {
            $this->rollbackWrite($started);
            throw $exception;
        }
    }

    public function findConnectionByTokenHash(string $tokenHash): ?array
    {
        $timestamps = $this->mysql
            ? 'UNIX_TIMESTAMP(connected_at) AS connected_at, UNIX_TIMESTAMP(last_seen_at) AS last_seen_at, UNIX_TIMESTAMP(expires_at) AS expires_at'
            : 'connected_at AS connected_at, last_seen_at AS last_seen_at, expires_at AS expires_at';
        $stmt = $this->pdo->prepare("SELECT *, $timestamps FROM devpilot_connector_connections WHERE token_hash=? LIMIT 1");
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return null;
        $row['scopes'] = json_decode((string) $row['scopes_json'], true) ?: [];
        return $row;
    }

    public function findConnectionByProject(string $projectId): ?array
    {
        $timestamps = $this->mysql
            ? 'UNIX_TIMESTAMP(connected_at) AS connected_at, UNIX_TIMESTAMP(last_seen_at) AS last_seen_at, UNIX_TIMESTAMP(expires_at) AS expires_at, UNIX_TIMESTAMP(revoked_at) AS revoked_at'
            : 'connected_at AS connected_at, last_seen_at AS last_seen_at, expires_at AS expires_at, revoked_at AS revoked_at';
        $stmt = $this->pdo->prepare("SELECT connection_id, project_id, project_name, origin, scopes_json, status, owner_id, $timestamps FROM devpilot_connector_connections WHERE project_id=? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return null;
        $row['scopes'] = json_decode((string) $row['scopes_json'], true) ?: [];
        return $row;
    }

    public function touchConnection(string $connectionId, int $now): void
    {
        $time = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
        $stmt = $this->pdo->prepare("UPDATE devpilot_connector_connections SET last_seen_at=$time WHERE connection_id=? AND status='active'");
        $stmt->execute([$now,$connectionId]);
    }

    public function revokeConnection(string $connectionId, int $now): void
    {
        $time = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
        $stmt = $this->pdo->prepare("UPDATE devpilot_connector_connections SET status='revoked', revoked_at=$time WHERE connection_id=? AND status='active'");
        $stmt->execute([$now,$connectionId]);
    }

    public function rotateConnection(string $connectionId, array $connection, int $now): void
    {
        $started = $this->beginWrite();
        try { $this->revokeConnection($connectionId,$now); $this->saveConnection($connection); $this->commitWrite($started); }
        catch (\Throwable $exception) { $this->rollbackWrite($started); throw $exception; }
    }

    public function pairingAllowed(string $ipHash, int $now): bool
    {
        $time = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM devpilot_connector_attempts WHERE ip_hash=? AND attempted_at >= $time");
        $stmt->execute([$ipHash,$now - 300]);
        return (int) $stmt->fetchColumn() < 10;
    }

    public function recordPairingFailure(string $ipHash, int $now): void
    {
        $time = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
        $cleanup = $this->pdo->prepare("DELETE FROM devpilot_connector_attempts WHERE attempted_at < $time");
        $cleanup->execute([$now - 86400]);
        $insert = $this->pdo->prepare("INSERT INTO devpilot_connector_attempts (ip_hash, attempted_at) VALUES (?, $time)");
        $insert->execute([$ipHash,$now]);
    }

    private function beginWrite(): bool
    {
        if ($this->pdo->inTransaction()) return false;
        if (!$this->mysql) $this->pdo->exec('BEGIN IMMEDIATE');
        else $this->pdo->beginTransaction();
        return true;
    }

    private function commitWrite(bool $started): void
    {
        if (!$started) return;
        if (!$this->mysql) $this->pdo->exec('COMMIT');
        else $this->pdo->commit();
    }

    private function rollbackWrite(bool $started): void
    {
        if (!$started || !$this->pdo->inTransaction()) return;
        if (!$this->mysql) $this->pdo->exec('ROLLBACK');
        else $this->pdo->rollBack();
    }
}
