<?php
declare(strict_types=1);

namespace DevPilot\Website;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class WebsiteRegistrationService
{
    private const ENVIRONMENTS = ['demo', 'staging', 'production', 'unknown'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly WebsiteRepository $repository,
        private readonly WebsiteUrlPolicy $urlPolicy,
        private readonly WebsiteScopePolicy $scopePolicy,
        private readonly CredentialVault $vault,
        private readonly WebsiteAuditLogger $audit,
        private readonly WebsiteAuth $auth
    ) {
    }

    public function register(array $input, int $ownerId): int
    {
        $this->validateOwner($ownerId);
        $name = trim(is_string($input['name'] ?? null) ? $input['name'] : '');
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($name === '' || $nameLength > 120) {
            throw new InvalidArgumentException('Enter a website name up to 120 characters.');
        }
        $url = $this->urlPolicy->normalize(is_string($input['admin_url'] ?? null) ? $input['admin_url'] : '');
        $environment = $input['environment'] ?? 'unknown';
        if (!is_string($environment) || !in_array($environment, self::ENVIRONMENTS, true)) {
            throw new InvalidArgumentException('Select a supported website environment.');
        }
        $permissionInput = $input['permissions'] ?? [];
        if (!is_array($permissionInput)) {
            throw new InvalidArgumentException('Permissions must be a list.');
        }
        $permissions = $this->scopePolicy->normalize($permissionInput);
        $mfaExpected = $this->normalizeBoolean($input['mfa_expected'] ?? false);
        $credential = $this->normalizeCredential($input['credentials'] ?? []);

        $websiteUuid = random_bytes(16);
        $credentialUuid = random_bytes(16);
        try {
            $this->pdo->beginTransaction();
            try {
                $site = $url + [
                    'website_uuid' => $websiteUuid,
                    'name' => $name,
                    'environment' => $environment,
                    'mfa_expected' => $mfaExpected,
                ];
                $websiteId = $this->repository->create($site, $ownerId);
                // This service has no HTTP client; registration never contacts the site.
                $encrypted = $this->vault->encrypt($websiteId, $credentialUuid, $credential);
                $this->repository->insertCredential($websiteId, 1, $encrypted);
                foreach ($permissions as $permission) {
                    $this->repository->addScopeGrant($websiteId, $permission, $ownerId);
                }
                $this->audit->record($ownerId, $websiteId, 'website_registered', 'success', [
                    'permission_codes' => $permissions,
                    'credential_version' => 1,
                ]);
                $this->pdo->commit();
                return $websiteId;
            } catch (Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $exception;
            }
        } finally {
            if (isset($input['credentials']['password']) && is_string($input['credentials']['password'])) {
                sodium_memzero($input['credentials']['password']);
            }
            if (isset($input['credentials']['username']) && is_string($input['credentials']['username'])) {
                sodium_memzero($input['credentials']['username']);
            }
            if (isset($credential['password']) && is_string($credential['password'])) {
                sodium_memzero($credential['password']);
            }
            if (isset($credential['username']) && is_string($credential['username'])) {
                sodium_memzero($credential['username']);
            }
        }
    }

    public function replaceScopes(int $websiteId, int $ownerId, array $permissions): void
    {
        $this->validateOwner($ownerId);
        $permissions = $this->scopePolicy->normalize($permissions);
        $this->pdo->beginTransaction();
        try {
            $this->repository->assertOwnedActiveForUpdate($websiteId, $ownerId);
            $this->repository->revokeScopes($websiteId);
            foreach ($permissions as $permission) {
                $this->repository->addScopeGrant($websiteId, $permission, $ownerId);
            }
            $this->audit->record($ownerId, $websiteId, 'scope_replaced', 'success', ['permission_codes' => $permissions]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function replaceCredential(int $websiteId, int $ownerId, array $input): void
    {
        $this->validateOwner($ownerId);
        $credential = $this->normalizeCredential($input);
        $credentialUuid = random_bytes(16);
        $this->pdo->beginTransaction();
        try {
            $this->repository->assertOwnedActiveForUpdate($websiteId, $ownerId);
            $version = $this->repository->nextCredentialVersion($websiteId);
            $encrypted = $this->vault->encrypt($websiteId, $credentialUuid, $credential);
            $newId = $this->repository->insertCredential($websiteId, $version, $encrypted);
            $this->repository->revokeActiveCredentials($websiteId, $newId, $newId);
            $this->audit->record($ownerId, $websiteId, 'credential_replaced', 'success', ['credential_version' => $version]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        } finally {
            if (isset($input['password']) && is_string($input['password'])) {
                sodium_memzero($input['password']);
            }
            if (isset($input['username']) && is_string($input['username'])) {
                sodium_memzero($input['username']);
            }
            if (isset($credential['password']) && is_string($credential['password'])) {
                sodium_memzero($credential['password']);
            }
            if (isset($credential['username']) && is_string($credential['username'])) {
                sodium_memzero($credential['username']);
            }
        }
    }

    public function revokeCredential(int $websiteId, int $ownerId): void
    {
        $this->validateOwner($ownerId);
        $this->pdo->beginTransaction();
        try {
            $site = $this->auth->requireOwned($websiteId, $ownerId, false, true);
            $this->repository->revokeActiveCredentials($websiteId);
            $this->audit->record($ownerId, $websiteId, 'credential_revoked', 'success');
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function revokeWebsite(int $websiteId, int $ownerId): void
    {
        $this->validateOwner($ownerId);
        $this->pdo->beginTransaction();
        try {
            $this->auth->requireOwned($websiteId, $ownerId, false, true);
            $this->repository->revokeWebsite($websiteId);
            $this->repository->revokeScopes($websiteId);
            $this->repository->revokeActiveCredentials($websiteId);
            $this->audit->record($ownerId, $websiteId, 'website_revoked', 'success');
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function detail(int $websiteId, int $ownerId): array
    {
        $this->validateOwner($ownerId);
        $site = $this->auth->requireOwned($websiteId, $ownerId);
        $grants = $this->repository->currentScopes($websiteId);
        $credential = $this->repository->credentialStatus($websiteId);
        $history = $this->repository->auditHistory($websiteId, $ownerId);
        return ['website' => $site, 'scopes' => $grants, 'credential' => $credential, 'audit' => $history];
    }

    public function list(int $ownerId): array
    {
        $this->validateOwner($ownerId);
        return $this->repository->listOwned($ownerId);
    }

    private function normalizeCredential(mixed $input): array
    {
        if (!is_array($input)) {
            throw new InvalidArgumentException('Enter website credentials.');
        }
        $username = $input['username'] ?? null;
        $password = $input['password'] ?? null;
        if (!is_string($username) || trim($username) === '' || strlen($username) > 320
            || !is_string($password) || $password === '' || strlen($password) > 4096) {
            throw new InvalidArgumentException('Enter a valid username and password.');
        }
        $normalized = ['username' => trim($username), 'password' => $password];
        sodium_memzero($input['username']);
        sodium_memzero($input['password']);
        return $normalized;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'on' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === null || $value === false || $value === '' || $value === 'false') {
            return false;
        }
        throw new InvalidArgumentException('MFA expectation value is invalid.');
    }

    private function validateOwner(int $ownerId): void
    {
        if ($ownerId <= 0) {
            throw new RuntimeException('Administrator authentication is required.');
        }
    }
}
