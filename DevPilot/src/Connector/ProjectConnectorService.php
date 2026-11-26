<?php
declare(strict_types=1);

namespace DevPilot\Connector;

use RuntimeException;

final class ProjectConnectorService
{
    public function __construct(private readonly ConnectorStore $store, private readonly int $pairingTtl = 300, private readonly int $tokenTtl = 2592000)
    {
        if ($pairingTtl < 30 || $tokenTtl < 300) throw new RuntimeException('Connector lifetime configuration is unsafe.');
    }

    public function beginPairing(string $projectId, string $projectName, string $origin, array $scopes, int $now, int $ownerId = 0): array
    {
        if (!preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $projectId) || trim($projectName) === '') throw new RuntimeException('Project identity is invalid.');
        $origin = ConnectorPolicy::origin($origin);
        $scopes = ConnectorPolicy::scopes($scopes);
        $code = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $this->store->createPairing(['code_hash' => hash('sha256', $code, true), 'project_id' => $projectId, 'project_name' => trim($projectName), 'origin' => $origin, 'scopes' => $scopes, 'expires_at' => $now + $this->pairingTtl, 'used_at' => null, 'owner_id' => $ownerId]);
        return ['pairing_code' => $code, 'expires_at' => $now + $this->pairingTtl, 'project_id' => $projectId, 'origin' => $origin, 'scopes' => $scopes];
    }

    /** Consumes the pairing code before issuing the token, making replay fail closed. */
    public function authorize(string $code, string $projectId, string $projectName, string $origin, array $scopes, int $now): array
    {
        $row = $this->store->consumePairing(hash('sha256', $code, true), $now);
        if ($row === null || !hash_equals($row['project_id'], $projectId) || !hash_equals($row['origin'], ConnectorPolicy::origin($origin)) || !hash_equals($row['project_name'], trim($projectName))) throw new RuntimeException('Pairing authorization failed.');
        $requested = ConnectorPolicy::scopes($scopes);
        foreach ($requested as $scope) if (!in_array($scope, $row['scopes'], true)) throw new RuntimeException('Requested permission was not granted.');
        $token = 'dvp_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $connection = ['connection_id' => bin2hex(random_bytes(16)), 'project_id' => $projectId, 'project_name' => trim($projectName), 'origin' => $row['origin'], 'scopes' => $requested, 'token_hash' => hash('sha256', $token, true), 'status' => 'active', 'connected_at' => $now, 'last_seen_at' => $now, 'expires_at' => $now + $this->tokenTtl, 'revoked_at' => null, 'owner_id' => (int) ($row['owner_id'] ?? 0)];
        $this->store->saveConnection($connection);
        return ['access_token' => $token, 'connection_id' => $connection['connection_id'], 'project_id' => $projectId, 'project_name' => $connection['project_name'], 'origin' => $connection['origin'], 'scopes' => $requested, 'connected_at' => $now, 'expires_at' => $connection['expires_at']];
    }

    public function inspect(string $token, int $now): array
    {
        $connection = $this->requireConnection($token, self::scope(), $now);
        return $this->inspection($connection, $now);
    }

    public function inspectForProject(string $token, string $projectId, string $origin, string $framework, int $now): array
    {
        $connection = $this->requireConnection($token, self::scope(), $now);
        if (!hash_equals($connection['project_id'], $projectId) || !hash_equals($connection['origin'], ConnectorPolicy::origin($origin))) throw new RuntimeException('Connector authorization failed.');
        $inspection = $this->inspection($connection, $now);
        $inspection['framework'] = $framework;
        return $inspection;
    }

    public function pairingAllowed(string $remoteKey, int $now): bool { return $this->store->pairingAllowed(hash('sha256', $remoteKey, true), $now); }
    public function recordPairingFailure(string $remoteKey, int $now): void { $this->store->recordPairingFailure(hash('sha256', $remoteKey, true), $now); }

    public function revoke(string $token, int $now): void { $this->store->revokeConnection($this->requireConnection($token, null, $now)['connection_id'], $now); }
    public function revokeForProject(string $token, string $projectId, string $origin, int $now): void
    {
        $connection = $this->requireConnection($token, null, $now);
        if (!hash_equals($connection['project_id'], $projectId) || !hash_equals($connection['origin'], ConnectorPolicy::origin($origin))) throw new RuntimeException('Connector authorization failed.');
        $this->store->revokeConnection($connection['connection_id'], $now);
    }
    public function status(string $projectId, int $ownerId): ?array
    {
        $row = $this->store->findConnectionByProject($projectId, $ownerId);
        if ($row === null || (int) ($row['owner_id'] ?? 0) !== $ownerId) return null;
        unset($row['token_hash'], $row['scopes_json']);
        return $row;
    }
    public function rotate(string $token, int $now): array
    {
        $old = $this->requireConnection($token, null, $now);
        $new = $this->authorizeForRotation($old, $now);
        $this->store->rotateConnection($old['connection_id'], $new['connection'], $now);
        return $new['response'];
    }
    private function authorizeForRotation(array $old, int $now): array
    {
        $token = 'dvp_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $connection = $old; $connection['connection_id'] = bin2hex(random_bytes(16)); $connection['token_hash'] = hash('sha256', $token, true); $connection['connected_at'] = $now; $connection['last_seen_at'] = $now; $connection['expires_at'] = $now + $this->tokenTtl; $connection['revoked_at'] = null;
        return ['connection' => $connection, 'response' => ['access_token' => $token, 'connection_id' => $connection['connection_id'], 'project_id' => $connection['project_id'], 'project_name' => $connection['project_name'], 'origin' => $connection['origin'], 'scopes' => $connection['scopes'], 'connected_at' => $now, 'expires_at' => $connection['expires_at']]];
    }
    private function requireConnection(string $token, ?string $scope, int $now): array
    {
        if ($token === '' || strlen($token) < 20) throw new RuntimeException('Connector authentication failed.');
        $row = $this->store->findConnectionByTokenHash(hash('sha256', $token, true));
        if ($row === null || $row['status'] !== 'active' || $row['expires_at'] <= $now || ($scope !== null && !in_array($scope, $row['scopes'], true))) throw new RuntimeException('Connector authorization failed.');
        $this->store->touchConnection($row['connection_id'], $now); $row['last_seen_at'] = $now;
        return $row;
    }
    private function inspection(array $connection, int $now): array
    {
        return ['project_id' => $connection['project_id'], 'project_name' => $connection['project_name'], 'origin' => $connection['origin'], 'technology' => 'PHP', 'framework' => 'PHP', 'runtime' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, 'application_version' => null, 'capabilities' => ['project.inspect.read'], 'scopes' => $connection['scopes'], 'inspected_at' => $now, 'connection_health' => 'healthy', 'connected_at' => $connection['connected_at'], 'last_seen_at' => $now];
    }
    private static function scope(): string { return ConnectorPolicy::INSPECT; }
}
