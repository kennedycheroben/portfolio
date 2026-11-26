<?php
declare(strict_types=1);

namespace DevPilot\Connector;

use DevPilot\Website\CredentialVault;
use PDO;
use RuntimeException;

/** DevPilot-side owner-scoped project registry. Access tokens are encrypted at rest. */
final class ConnectedProjectStore
{
    public function __construct(private readonly PDO $pdo, private readonly CredentialVault $vault) {}

    public function save(int $ownerId, array $connection, string $token, array $inspection, int $now): void
    {
        if ($ownerId <= 0 || $token === '') throw new RuntimeException('Project ownership or credentials are unavailable.');
        $uuid = random_bytes(16);
        $encrypted = $this->vault->encrypt($ownerId, $uuid, ['username' => 'connector', 'password' => $token]);
        $safeInspection = array_intersect_key($inspection, array_flip(['project_id','project_name','origin','technology','framework','runtime','application_version','capabilities','scopes','inspected_at','connection_health','connected_at','last_seen_at']));
        $sql = 'INSERT INTO devpilot_connected_projects (owner_id, project_id, connection_id, project_name, origin, scopes_json, status, credential_uuid, cipher_algorithm, data_key_version, master_key_version, payload_nonce, payload_ciphertext, wrapped_dek_nonce, wrapped_dek, connected_at, last_seen_at, last_inspected_at, inspection_json, last_error, updated_at) VALUES (?, ?, ?, ?, ?, ?, \'active\', ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), FROM_UNIXTIME(?), ?, NULL, FROM_UNIXTIME(?)) ON DUPLICATE KEY UPDATE connection_id=VALUES(connection_id), project_name=VALUES(project_name), origin=VALUES(origin), scopes_json=VALUES(scopes_json), status=\'active\', credential_uuid=VALUES(credential_uuid), cipher_algorithm=VALUES(cipher_algorithm), data_key_version=VALUES(data_key_version), master_key_version=VALUES(master_key_version), payload_nonce=VALUES(payload_nonce), payload_ciphertext=VALUES(payload_ciphertext), wrapped_dek_nonce=VALUES(wrapped_dek_nonce), wrapped_dek=VALUES(wrapped_dek), connected_at=VALUES(connected_at), last_seen_at=VALUES(last_seen_at), last_inspected_at=VALUES(last_inspected_at), inspection_json=VALUES(inspection_json), last_error=NULL, updated_at=VALUES(updated_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ownerId,$connection['project_id'],$connection['connection_id'],$connection['project_name'],$connection['origin'],json_encode($connection['scopes'], JSON_THROW_ON_ERROR),$uuid,$encrypted['cipher_algorithm'],$encrypted['data_key_version'],$encrypted['master_key_version'],$encrypted['payload_nonce'],$encrypted['payload_ciphertext'],$encrypted['wrapped_dek_nonce'],$encrypted['wrapped_dek'],$now,$now,$now,json_encode($safeInspection, JSON_THROW_ON_ERROR),$now]);
    }

    public function status(int $ownerId, string $projectId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT project_id, connection_id, project_name, origin, scopes_json, status, UNIX_TIMESTAMP(connected_at) AS connected_at, UNIX_TIMESTAMP(last_seen_at) AS last_seen_at, UNIX_TIMESTAMP(last_inspected_at) AS last_inspected_at, inspection_json, last_error FROM devpilot_connected_projects WHERE owner_id=? AND project_id=? LIMIT 1');
        $stmt->execute([$ownerId,$projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return null;
        $row['scopes'] = json_decode((string) $row['scopes_json'], true) ?: [];
        $row['inspection'] = json_decode((string) ($row['inspection_json'] ?? ''), true) ?: null;
        unset($row['scopes_json'], $row['inspection_json']);
        return $row;
    }

    public function token(int $ownerId, string $projectId): string
    {
        $stmt = $this->pdo->prepare('SELECT * FROM devpilot_connected_projects WHERE owner_id=? AND project_id=? AND status=\'active\' LIMIT 1');
        $stmt->execute([$ownerId,$projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('Project connection is unavailable.');
        $credentials = $this->vault->decrypt($ownerId, [
            'credential_uuid'=>$row['credential_uuid'],'cipher_algorithm'=>$row['cipher_algorithm'],'data_key_version'=>$row['data_key_version'],'master_key_version'=>$row['master_key_version'],
            'payload_nonce'=>$row['payload_nonce'],'payload_ciphertext'=>$row['payload_ciphertext'],'wrapped_dek_nonce'=>$row['wrapped_dek_nonce'],'wrapped_dek'=>$row['wrapped_dek'],'status'=>'active',
        ]);
        return $credentials['password'];
    }

    public function updateInspection(int $ownerId, string $projectId, array $inspection, int $now): void
    {
        $safe = array_intersect_key($inspection, array_flip(['project_id','project_name','origin','technology','framework','runtime','application_version','capabilities','scopes','inspected_at','connection_health','connected_at','last_seen_at']));
        $stmt = $this->pdo->prepare('UPDATE devpilot_connected_projects SET inspection_json=?, last_inspected_at=FROM_UNIXTIME(?), last_seen_at=FROM_UNIXTIME(?), last_error=NULL, updated_at=FROM_UNIXTIME(?) WHERE owner_id=? AND project_id=? AND status=\'active\'');
        $stmt->execute([json_encode($safe, JSON_THROW_ON_ERROR),$now,$now,$now,$ownerId,$projectId]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Project connection is unavailable.');
    }

    public function recordInspectionFailure(int $ownerId, string $projectId, int $now): void
    {
        $stmt = $this->pdo->prepare('UPDATE devpilot_connected_projects SET last_error=\'Inspection request failed; reconnect or retry.\', updated_at=FROM_UNIXTIME(?) WHERE owner_id=? AND project_id=? AND status=\'active\'');
        $stmt->execute([$now,$ownerId,$projectId]);
    }

    public function disconnect(int $ownerId, string $projectId, int $now): void
    {
        $stmt = $this->pdo->prepare('UPDATE devpilot_connected_projects SET status=\'revoked\', last_error=NULL, updated_at=FROM_UNIXTIME(?) WHERE owner_id=? AND project_id=? AND status=\'active\'');
        $stmt->execute([$now,$ownerId,$projectId]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Project connection is unavailable.');
    }
}
