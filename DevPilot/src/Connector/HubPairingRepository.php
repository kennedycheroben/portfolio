<?php
declare(strict_types=1);

namespace DevPilot\Connector;

use DevPilot\Website\CredentialVault;
use PDO;
use RuntimeException;

/** Stores pending cross-instance approvals on the Hub; pairing codes are hashed and encrypted at rest. */
final class HubPairingRepository
{
    private readonly bool $mysql;

    public function __construct(private readonly PDO $pdo, private readonly CredentialVault $vault)
    {
        $this->mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    public function create(array $request, string $pairingCode, string $remoteAddress, int $now): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{32}$/', $pairingCode)) throw new RuntimeException('Project pairing code is invalid.');
        $origin = ConnectorPolicy::origin((string) ($request['origin'] ?? ''));
        $scopes = ConnectorPolicy::scopes((array) ($request['scopes'] ?? []));
        if (!in_array(ConnectorPolicy::INSPECT, $scopes, true)) throw new RuntimeException('Inspection scope is required.');
        $projectId = (string) ($request['project_id'] ?? '');
        $projectName = trim((string) ($request['project_name'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $projectId) || $projectName === '' || strlen($projectName) > 160) throw new RuntimeException('Project identity is invalid.');
        $returnUrl = (string) ($request['return_url'] ?? '');
        if ($returnUrl !== $origin . '/admin/devpilot-websites.php') throw new RuntimeException('Project return address is invalid.');

        $ipHash = hash('sha256',$remoteAddress,true);
        $window = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
        $rate = $this->pdo->prepare("SELECT COUNT(*) FROM devpilot_hub_pairing_requests WHERE request_ip_hash=? AND created_at >= $window");
        $rate->execute([$ipHash,$now - 300]);
        if ((int) $rate->fetchColumn() >= 10) throw new RuntimeException('Too many pairing requests. Try again in a few minutes.');

        $id = bin2hex(random_bytes(16));
        $uuid = random_bytes(16);
        $encrypted = $this->vault->encrypt(1, $uuid, ['username'=>'pairing','password'=>$pairingCode]);
        $stamp = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
        $stmt = $this->pdo->prepare("INSERT INTO devpilot_hub_pairing_requests (request_id, pairing_code_hash, project_id, project_name, origin, scopes_json, return_url, request_ip_hash, status, credential_uuid, cipher_algorithm, data_key_version, master_key_version, payload_nonce, payload_ciphertext, wrapped_dek_nonce, wrapped_dek, expires_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, $stamp, $stamp, $stamp)");
        $stmt->execute([$id,hash('sha256',$pairingCode,true),$projectId,$projectName,$origin,json_encode($scopes,JSON_THROW_ON_ERROR),$returnUrl,$ipHash,$uuid,$encrypted['cipher_algorithm'],$encrypted['data_key_version'],$encrypted['master_key_version'],$encrypted['payload_nonce'],$encrypted['payload_ciphertext'],$encrypted['wrapped_dek_nonce'],$encrypted['wrapped_dek'],$now + 300,$now,$now]);
        return $id;
    }

    public function pending(string $requestId, int $now): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM devpilot_hub_pairing_requests WHERE request_id=? AND status='pending' AND expires_at>? LIMIT 1");
        $stmt->execute([$requestId,$this->mysql ? gmdate('Y-m-d H:i:s',$now) : $now]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('Pairing request is expired or unavailable.');
        $row['scopes'] = json_decode((string) $row['scopes_json'], true) ?: [];
        return $row;
    }

    public function decryptCode(array $row): string
    {
        $credentials = $this->vault->decrypt(1, [
            'credential_uuid'=>$row['credential_uuid'],'cipher_algorithm'=>$row['cipher_algorithm'],'data_key_version'=>$row['data_key_version'],'master_key_version'=>$row['master_key_version'],
            'payload_nonce'=>$row['payload_nonce'],'payload_ciphertext'=>$row['payload_ciphertext'],'wrapped_dek_nonce'=>$row['wrapped_dek_nonce'],'wrapped_dek'=>$row['wrapped_dek'],'status'=>'active',
        ]);
        return $credentials['password'];
    }

    public function claim(string $requestId, int $ownerId, int $now): ?array
    {
        if ($ownerId <= 0) throw new RuntimeException('Hub administrator identity is unavailable.');
        $started = !$this->pdo->inTransaction();
        if ($started) {
            if (!$this->mysql) $this->pdo->exec('BEGIN IMMEDIATE'); else $this->pdo->beginTransaction();
        }
        try {
            $time = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
            $stmt = $this->pdo->prepare("UPDATE devpilot_hub_pairing_requests SET status='approving', owner_id=?, updated_at=$time WHERE request_id=? AND status='pending' AND expires_at>$time");
            $stmt->execute([$ownerId,$now,$requestId,$now]);
            if ($stmt->rowCount() !== 1) { if ($started) $this->rollback(); return null; }
            $this->commit($started);
            return $this->pendingForApproval($requestId);
        } catch (\Throwable $exception) {
            if ($started) $this->rollback();
            throw $exception;
        }
    }

    public function complete(string $requestId, int $now): void { $this->finish($requestId,'completed',$now); }
    public function fail(string $requestId, int $now): void { $this->finish($requestId,'failed',$now); }
    public function reject(string $requestId, int $now): void
    {
        $time = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
        $stmt = $this->pdo->prepare("UPDATE devpilot_hub_pairing_requests SET status='rejected', credential_uuid=NULL, cipher_algorithm=NULL, data_key_version=NULL, master_key_version=NULL, payload_nonce=NULL, payload_ciphertext=NULL, wrapped_dek_nonce=NULL, wrapped_dek=NULL, updated_at=$time WHERE request_id=? AND status='pending'");
        $stmt->execute([$now,$requestId]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Pairing request could not be rejected.');
    }

    public function listPending(): array
    {
        $predicate = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
        $stmt = $this->pdo->prepare("SELECT request_id, project_id, project_name, origin, scopes_json, status, expires_at FROM devpilot_hub_pairing_requests WHERE status='pending' AND expires_at>$predicate ORDER BY created_at DESC");
        $stmt->execute([time()]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) { $row['scopes'] = json_decode((string) $row['scopes_json'],true) ?: []; unset($row['scopes_json']); }
        unset($row);
        return $rows;
    }

    private function pendingForApproval(string $requestId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM devpilot_hub_pairing_requests WHERE request_id=? AND status='approving' LIMIT 1");
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('Pairing request is unavailable.');
        $row['scopes'] = json_decode((string) $row['scopes_json'],true) ?: [];
        return $row;
    }

    private function finish(string $requestId, string $status, int $now): void
    {
        $time = $this->mysql ? 'FROM_UNIXTIME(?)' : '?';
        $stmt = $this->pdo->prepare("UPDATE devpilot_hub_pairing_requests SET status=?, credential_uuid=NULL, cipher_algorithm=NULL, data_key_version=NULL, master_key_version=NULL, payload_nonce=NULL, payload_ciphertext=NULL, wrapped_dek_nonce=NULL, wrapped_dek=NULL, updated_at=$time WHERE request_id=? AND status='approving'");
        $stmt->execute([$status,$now,$requestId]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Pairing request could not be finalized.');
    }

    private function commit(bool $started): void { if (!$started) return; if ($this->mysql) $this->pdo->commit(); else $this->pdo->exec('COMMIT'); }
    private function rollback(): void { if (!$this->pdo->inTransaction()) return; if ($this->mysql) $this->pdo->rollBack(); else $this->pdo->exec('ROLLBACK'); }
}
