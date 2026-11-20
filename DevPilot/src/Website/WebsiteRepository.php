<?php
declare(strict_types=1);

namespace DevPilot\Website;

use PDO;
use RuntimeException;

final class WebsiteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listOwned(int $ownerId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT w.id, HEX(w.website_uuid) AS website_uuid, w.name, w.admin_url, w.origin_host, w.origin_port, w.base_path, w.environment, w.mfa_expected, w.status, w.created_at, w.revoked_at,
                    (SELECT COUNT(*) FROM devpilot_website_scopes s WHERE s.website_id = w.id AND s.revoked_at IS NULL) AS active_scope_count,
                    (SELECT c.status FROM devpilot_website_credentials c WHERE c.website_id = w.id ORDER BY c.credential_version DESC LIMIT 1) AS credential_status
             FROM devpilot_websites w WHERE w.owner_user_id = ? ORDER BY w.created_at DESC, w.id DESC'
        );
        $statement->execute([$ownerId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findOwned(int $websiteId, int $ownerId, bool $lock = false): ?array
    {
        $sql = 'SELECT id, HEX(website_uuid) AS website_uuid, owner_user_id, name, admin_url, origin_host, origin_port, base_path, environment, mfa_expected, status, created_at, updated_at, revoked_at FROM devpilot_websites WHERE id = ? AND owner_user_id = ?';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute([$websiteId, $ownerId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(array $site, int $ownerId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO devpilot_websites (website_uuid, owner_user_id, name, admin_url, origin_host, origin_port, base_path, origin_fingerprint, active_origin_fingerprint, environment, mfa_expected, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\')'
        );
        $statement->bindValue(1, $site['website_uuid'], PDO::PARAM_LOB);
        $statement->bindValue(2, $ownerId, PDO::PARAM_INT);
        $statement->bindValue(3, $site['name'], PDO::PARAM_STR);
        $statement->bindValue(4, $site['admin_url'], PDO::PARAM_STR);
        $statement->bindValue(5, $site['origin_host'], PDO::PARAM_STR);
        $statement->bindValue(6, $site['origin_port'], PDO::PARAM_INT);
        $statement->bindValue(7, $site['base_path'], PDO::PARAM_STR);
        $statement->bindValue(8, $site['origin_fingerprint'], PDO::PARAM_LOB);
        $statement->bindValue(9, $site['origin_fingerprint'], PDO::PARAM_LOB);
        $statement->bindValue(10, $site['environment'], PDO::PARAM_STR);
        $statement->bindValue(11, $site['mfa_expected'] ? 1 : 0, PDO::PARAM_INT);
        $statement->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function currentScopes(int $websiteId): array
    {
        $statement = $this->pdo->prepare('SELECT grant_id, website_id, permission_code, constraints_json, granted_by, granted_at, revoked_at FROM devpilot_website_scopes WHERE website_id = ? ORDER BY grant_id DESC');
        $statement->execute([$websiteId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addScopeGrant(int $websiteId, string $permission, int $actorId): void
    {
        $statement = $this->pdo->prepare('INSERT INTO devpilot_website_scopes (website_id, permission_code, constraints_json, granted_by) VALUES (?, ?, ?, ?)');
        $statement->execute([$websiteId, $permission, '{}', $actorId]);
    }

    public function revokeScopes(int $websiteId): void
    {
        $statement = $this->pdo->prepare('UPDATE devpilot_website_scopes SET revoked_at = CURRENT_TIMESTAMP WHERE website_id = ? AND revoked_at IS NULL');
        $statement->execute([$websiteId]);
    }

    public function nextCredentialVersion(int $websiteId): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(MAX(credential_version), 0) + 1 FROM devpilot_website_credentials WHERE website_id = ?');
        $statement->execute([$websiteId]);
        return (int) $statement->fetchColumn();
    }

    public function insertCredential(int $websiteId, int $version, array $encrypted): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO devpilot_website_credentials
             (credential_uuid, website_id, credential_version, credential_kind, cipher_algorithm, data_key_version, master_key_version, payload_nonce, payload_ciphertext, wrapped_dek_nonce, wrapped_dek, status)
             VALUES (?, ?, ?, \'web_login\', ?, ?, ?, ?, ?, ?, ?, \'active\')'
        );
        $statement->bindValue(1, $encrypted['credential_uuid'], PDO::PARAM_LOB);
        $statement->bindValue(2, $websiteId, PDO::PARAM_INT);
        $statement->bindValue(3, $version, PDO::PARAM_INT);
        $statement->bindValue(4, $encrypted['cipher_algorithm'], PDO::PARAM_STR);
        $statement->bindValue(5, $encrypted['data_key_version'], PDO::PARAM_STR);
        $statement->bindValue(6, $encrypted['master_key_version'], PDO::PARAM_STR);
        $statement->bindValue(7, $encrypted['payload_nonce'], PDO::PARAM_LOB);
        $statement->bindValue(8, $encrypted['payload_ciphertext'], PDO::PARAM_LOB);
        $statement->bindValue(9, $encrypted['wrapped_dek_nonce'], PDO::PARAM_LOB);
        $statement->bindValue(10, $encrypted['wrapped_dek'], PDO::PARAM_LOB);
        $statement->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function revokeActiveCredentials(int $websiteId, ?int $replacementId = null, ?int $exceptCredentialId = null): void
    {
        $sql = 'UPDATE devpilot_website_credentials
                SET status = \'revoked\', revoked_at = CURRENT_TIMESTAMP,
                    payload_nonce = NULL, payload_ciphertext = NULL,
                    wrapped_dek_nonce = NULL, wrapped_dek = NULL,
                    replaced_by = ?
                WHERE website_id = ? AND status = \'active\'';
        $parameters = [$replacementId, $websiteId];
        if ($exceptCredentialId !== null) {
            $sql .= ' AND id <> ?';
            $parameters[] = $exceptCredentialId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
    }

    public function credentialStatus(int $websiteId): ?array
    {
        $statement = $this->pdo->prepare('SELECT credential_version, credential_kind, cipher_algorithm, master_key_version, status, created_at, revoked_at FROM devpilot_website_credentials WHERE website_id = ? ORDER BY credential_version DESC LIMIT 1');
        $statement->execute([$websiteId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function revokeWebsite(int $websiteId): void
    {
        $statement = $this->pdo->prepare('UPDATE devpilot_websites SET status = \'revoked\', active_origin_fingerprint = NULL, revoked_at = CURRENT_TIMESTAMP WHERE id = ? AND status = \'active\'');
        $statement->execute([$websiteId]);
    }

    public function audit(int $actorId, ?int $websiteId, string $event, string $outcome, array $details = []): void
    {
        $statement = $this->pdo->prepare('INSERT INTO devpilot_security_audit (actor_user_id, website_id, event_code, outcome, request_uuid, details_json) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->bindValue(1, $actorId, PDO::PARAM_INT);
        $statement->bindValue(2, $websiteId, $websiteId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(3, $event, PDO::PARAM_STR);
        $statement->bindValue(4, $outcome, PDO::PARAM_STR);
        $statement->bindValue(5, random_bytes(16), PDO::PARAM_LOB);
        $json = $details === [] ? null : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $statement->bindValue(6, $json, $json === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->execute();
    }

    public function auditHistory(int $websiteId, int $ownerId, int $limit = 100): array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.id, a.event_code, a.outcome, HEX(a.request_uuid) AS request_uuid, a.details_json, a.created_at, a.actor_user_id
             FROM devpilot_security_audit a JOIN devpilot_websites w ON w.id = a.website_id
             WHERE a.website_id = ? AND w.owner_user_id = ?
             ORDER BY a.id DESC LIMIT ?'
        );
        $statement->bindValue(1, $websiteId, PDO::PARAM_INT);
        $statement->bindValue(2, $ownerId, PDO::PARAM_INT);
        $statement->bindValue(3, max(1, min(200, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assertOwnedActiveForUpdate(int $websiteId, int $ownerId): array
    {
        $site = $this->findOwned($websiteId, $ownerId, true);
        if ($site === null) {
            throw new RuntimeException('Website was not found.');
        }
        if ($site['status'] !== 'active') {
            throw new RuntimeException('This website registration has been revoked.');
        }
        return $site;
    }
}
