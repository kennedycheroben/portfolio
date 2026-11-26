<?php
declare(strict_types=1);

namespace DevPilot\Connector;

use PDO;

final class PdoConnectorStore implements ConnectorStore
{
    public function __construct(private readonly PDO $pdo) {}
    public function createPairing(array $p): void { $s=$this->pdo->prepare('INSERT INTO devpilot_connector_pairings (code_hash, project_id, project_name, origin, scopes_json, expires_at, owner_id) VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?)'); $s->execute([$p['code_hash'],$p['project_id'],$p['project_name'],$p['origin'],json_encode($p['scopes'], JSON_THROW_ON_ERROR),$p['expires_at'],$p['owner_id'] ?? 0]); }
    public function consumePairing(string $hash, int $now): ?array
    {
        $this->pdo->beginTransaction();
        try { $s=$this->pdo->prepare('SELECT * FROM devpilot_connector_pairings WHERE code_hash = ? AND used_at IS NULL AND expires_at > FROM_UNIXTIME(?) FOR UPDATE'); $s->execute([$hash,$now]); $row=$s->fetch(PDO::FETCH_ASSOC); if (!is_array($row)) { $this->pdo->rollBack(); return null; } $u=$this->pdo->prepare('UPDATE devpilot_connector_pairings SET used_at = FROM_UNIXTIME(?) WHERE id = ? AND used_at IS NULL'); $u->execute([$now,$row['id']]); if ($u->rowCount() !== 1) { $this->pdo->rollBack(); return null; } $this->pdo->commit(); $row['scopes']=json_decode($row['scopes_json'],true) ?: []; return $row; }
        catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    public function saveConnection(array $c): void { $started = !$this->pdo->inTransaction(); if ($started) $this->pdo->beginTransaction(); try { $owner=(int)($c['owner_id'] ?? 0); $lock=$this->pdo->prepare('SELECT id FROM devpilot_connector_connections WHERE owner_id=? AND project_id=? FOR UPDATE'); $lock->execute([$owner,$c['project_id']]); $lock->fetchAll(PDO::FETCH_COLUMN); $r=$this->pdo->prepare('UPDATE devpilot_connector_connections SET status=\'revoked\', revoked_at=FROM_UNIXTIME(?) WHERE project_id=? AND owner_id=? AND status=\'active\''); $r->execute([$c['connected_at'],$c['project_id'],$owner]); $s=$this->pdo->prepare('INSERT INTO devpilot_connector_connections (connection_id, project_id, project_name, origin, scopes_json, token_hash, status, connected_at, last_seen_at, expires_at, owner_id) VALUES (?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), FROM_UNIXTIME(?), ?)'); $s->execute([$c['connection_id'],$c['project_id'],$c['project_name'],$c['origin'],json_encode($c['scopes'],JSON_THROW_ON_ERROR),$c['token_hash'],$c['status'],$c['connected_at'],$c['last_seen_at'],$c['expires_at'],$owner]); if ($started) $this->pdo->commit(); } catch (\Throwable $e) { if ($started && $this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; } }
    public function findConnectionByTokenHash(string $hash): ?array { $s=$this->pdo->prepare('SELECT *, UNIX_TIMESTAMP(connected_at) AS connected_at, UNIX_TIMESTAMP(last_seen_at) AS last_seen_at, UNIX_TIMESTAMP(expires_at) AS expires_at FROM devpilot_connector_connections WHERE token_hash = ? LIMIT 1'); $s->execute([$hash]); $r=$s->fetch(PDO::FETCH_ASSOC); if (!is_array($r)) return null; $r['scopes']=json_decode($r['scopes_json'],true) ?: []; return $r; }
    public function findConnectionByProject(string $projectId): ?array { $s=$this->pdo->prepare('SELECT connection_id, project_id, project_name, origin, scopes_json, status, UNIX_TIMESTAMP(connected_at) AS connected_at, UNIX_TIMESTAMP(last_seen_at) AS last_seen_at, UNIX_TIMESTAMP(expires_at) AS expires_at, UNIX_TIMESTAMP(revoked_at) AS revoked_at FROM devpilot_connector_connections WHERE project_id = ? ORDER BY id DESC LIMIT 1'); $s->execute([$projectId]); $r=$s->fetch(PDO::FETCH_ASSOC); if (!is_array($r)) return null; $r['scopes']=json_decode($r['scopes_json'],true) ?: []; return $r; }
    public function touchConnection(string $id,int $now):void { $s=$this->pdo->prepare('UPDATE devpilot_connector_connections SET last_seen_at=FROM_UNIXTIME(?) WHERE connection_id=? AND status=\'active\'');$s->execute([$now,$id]); }
    public function revokeConnection(string $id,int $now):void { $s=$this->pdo->prepare('UPDATE devpilot_connector_connections SET status=\'revoked\', revoked_at=FROM_UNIXTIME(?) WHERE connection_id=? AND status=\'active\'');$s->execute([$now,$id]); }
    public function rotateConnection(string $id,array $c,int $now):void { $this->pdo->beginTransaction(); try { $this->revokeConnection($id,$now); $this->saveConnection($c); $this->pdo->commit(); } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; } }
    public function pairingAllowed(string $key, int $now): bool
    {
        $q = $this->pdo->prepare('SELECT COUNT(*) FROM devpilot_connector_attempts WHERE ip_hash = ? AND attempted_at >= FROM_UNIXTIME(?)');
        $q->execute([$key, $now - 300]);
        return (int) $q->fetchColumn() < 10;
    }
    public function recordPairingFailure(string $key, int $now): void
    {
        $cleanup = $this->pdo->prepare('DELETE FROM devpilot_connector_attempts WHERE attempted_at < FROM_UNIXTIME(?)');
        $cleanup->execute([$now - 86400]);
        $i = $this->pdo->prepare('INSERT INTO devpilot_connector_attempts (ip_hash, attempted_at) VALUES (?, FROM_UNIXTIME(?))');
        $i->execute([$key, $now]);
    }
}
