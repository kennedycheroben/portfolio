<?php
declare(strict_types=1);

namespace DevPilot\Connector;

use PDO;

final class PdoConnectorStore implements ConnectorStore
{
    public function __construct(private readonly PDO $pdo) {}
    public function createPairing(array $p): void { $s=$this->pdo->prepare('INSERT INTO devpilot_connector_pairings (code_hash, project_id, project_name, origin, scopes_json, expires_at) VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?))'); $s->execute([$p['code_hash'],$p['project_id'],$p['project_name'],$p['origin'],json_encode($p['scopes'], JSON_THROW_ON_ERROR),$p['expires_at']]); }
    public function consumePairing(string $hash, int $now): ?array
    {
        $this->pdo->beginTransaction();
        try { $s=$this->pdo->prepare('SELECT * FROM devpilot_connector_pairings WHERE code_hash = ? AND used_at IS NULL AND expires_at >= FROM_UNIXTIME(?) FOR UPDATE'); $s->execute([$hash,$now]); $row=$s->fetch(PDO::FETCH_ASSOC); if (!is_array($row)) { $this->pdo->rollBack(); return null; } $u=$this->pdo->prepare('UPDATE devpilot_connector_pairings SET used_at = CURRENT_TIMESTAMP WHERE id = ? AND used_at IS NULL'); $u->execute([$row['id']]); if ($u->rowCount() !== 1) { $this->pdo->rollBack(); return null; } $this->pdo->commit(); $row['scopes']=json_decode($row['scopes_json'],true) ?: []; return $row; }
        catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    public function saveConnection(array $c): void { $s=$this->pdo->prepare('INSERT INTO devpilot_connector_connections (connection_id, project_id, project_name, origin, scopes_json, token_hash, status, connected_at, last_seen_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), FROM_UNIXTIME(?))'); $s->execute([$c['connection_id'],$c['project_id'],$c['project_name'],$c['origin'],json_encode($c['scopes'],JSON_THROW_ON_ERROR),$c['token_hash'],$c['status'],$c['connected_at'],$c['last_seen_at'],$c['expires_at']]); }
    public function findConnectionByTokenHash(string $hash): ?array { $s=$this->pdo->prepare('SELECT *, UNIX_TIMESTAMP(connected_at) AS connected_at, UNIX_TIMESTAMP(last_seen_at) AS last_seen_at, UNIX_TIMESTAMP(expires_at) AS expires_at FROM devpilot_connector_connections WHERE token_hash = ? LIMIT 1'); $s->execute([$hash]); $r=$s->fetch(PDO::FETCH_ASSOC); if (!is_array($r)) return null; $r['scopes']=json_decode($r['scopes_json'],true) ?: []; return $r; }
    public function findConnectionByProject(string $projectId): ?array { $s=$this->pdo->prepare('SELECT connection_id, project_id, project_name, origin, scopes_json, status, UNIX_TIMESTAMP(connected_at) AS connected_at, UNIX_TIMESTAMP(last_seen_at) AS last_seen_at, UNIX_TIMESTAMP(expires_at) AS expires_at, UNIX_TIMESTAMP(revoked_at) AS revoked_at FROM devpilot_connector_connections WHERE project_id = ? ORDER BY id DESC LIMIT 1'); $s->execute([$projectId]); $r=$s->fetch(PDO::FETCH_ASSOC); if (!is_array($r)) return null; $r['scopes']=json_decode($r['scopes_json'],true) ?: []; return $r; }
    public function touchConnection(string $id,int $now):void { $s=$this->pdo->prepare('UPDATE devpilot_connector_connections SET last_seen_at=FROM_UNIXTIME(?) WHERE connection_id=? AND status=\'active\'');$s->execute([$now,$id]); }
    public function revokeConnection(string $id,int $now):void { $s=$this->pdo->prepare('UPDATE devpilot_connector_connections SET status=\'revoked\', revoked_at=FROM_UNIXTIME(?) WHERE connection_id=? AND status=\'active\'');$s->execute([$now,$id]); }
    public function rotateConnection(string $id,array $c,int $now):void { $this->pdo->beginTransaction(); try { $this->revokeConnection($id,$now); $this->saveConnection($c); $this->pdo->commit(); } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; } }
}
