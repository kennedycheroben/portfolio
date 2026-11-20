<?php
declare(strict_types=1);

namespace DevPilot\Tests;

use DevPilot\Website\CredentialVault;
use DevPilot\Website\VaultKeyring;
use DevPilot\Website\WebsiteServices;
use PDO;
use PDOException;
use RuntimeException;

/** Runs only when DEVPILOT_TEST_DSN targets an explicitly named local test database. */
final class WebsitePersistenceTest
{
    public function testRegistrationOwnershipScopesReplacementRevocationAndAudit(): void
    {
        [$pdo, $service, $vault] = $this->freshFixture();
        $secret = 'phase1-fictional-password';
        $websiteId = $service->register([
            'name' => 'Disposable demo website',
            'admin_url' => 'https://no-outbound-check.example.org/admin',
            'environment' => 'demo',
            'mfa_expected' => true,
            'permissions' => ['inspect_dashboard', 'create_demo_accounts'],
            'credentials' => ['username' => 'demo-operator', 'password' => $secret],
        ], 1);

        $credentialRows = $pdo->query('SELECT * FROM devpilot_website_credentials WHERE website_id = ' . (int) $websiteId . ' ORDER BY credential_version')->fetchAll(PDO::FETCH_ASSOC);
        if (count($credentialRows) !== 1 || !is_string($credentialRows[0]['payload_ciphertext']) || str_contains($credentialRows[0]['payload_ciphertext'], $secret)) {
            throw new RuntimeException('Credential was not stored as ciphertext.');
        }
        $decrypted = $vault->decrypt($websiteId, $credentialRows[0]);
        if (($decrypted['password'] ?? null) !== $secret) {
            throw new RuntimeException('Persisted envelope could not be decrypted.');
        }
        sodium_memzero($decrypted['password']);
        sodium_memzero($decrypted['username']);

        $beforeSites = (int) $pdo->query('SELECT COUNT(*) FROM devpilot_websites')->fetchColumn();
        $beforeAudit = (int) $pdo->query('SELECT COUNT(*) FROM devpilot_security_audit')->fetchColumn();
        try {
            $service->register([
                'name' => 'Duplicate origin while active',
                'admin_url' => 'https://no-outbound-check.example.org/other-path',
                'environment' => 'demo',
                'permissions' => [],
                'credentials' => ['username' => 'another', 'password' => 'another-secret'],
            ], 1);
            throw new RuntimeException('Duplicate active website origin was accepted.');
        } catch (PDOException) {
            // Expected unique active-origin rejection.
        }
        if ((int) $pdo->query('SELECT COUNT(*) FROM devpilot_websites')->fetchColumn() !== $beforeSites
            || (int) $pdo->query('SELECT COUNT(*) FROM devpilot_security_audit')->fetchColumn() !== $beforeAudit) {
            throw new RuntimeException('Failed registration did not roll back as a unit.');
        }

        if ($service->list(2) !== []) {
            throw new RuntimeException('A different owner could list the website.');
        }
        $this->expectFailure(fn() => $service->detail($websiteId, 2));

        $service->replaceScopes($websiteId, 1, ['inspect_dashboard']);
        $scopeHistory = $pdo->query('SELECT permission_code, revoked_at FROM devpilot_website_scopes WHERE website_id = ' . (int) $websiteId . ' ORDER BY grant_id')->fetchAll(PDO::FETCH_ASSOC);
        if (count($scopeHistory) !== 3 || $scopeHistory[0]['revoked_at'] === null || $scopeHistory[1]['revoked_at'] === null || $scopeHistory[2]['revoked_at'] !== null) {
            throw new RuntimeException('Permission updates did not preserve grant history.');
        }

        $service->replaceCredential($websiteId, 1, ['username' => 'new-demo-operator', 'password' => 'replacement-secret']);
        $credentialRows = $pdo->query('SELECT * FROM devpilot_website_credentials WHERE website_id = ' . (int) $websiteId . ' ORDER BY credential_version')->fetchAll(PDO::FETCH_ASSOC);
        if (count($credentialRows) !== 2
            || $credentialRows[0]['status'] !== 'revoked'
            || $credentialRows[0]['payload_ciphertext'] !== null
            || $credentialRows[0]['wrapped_dek'] !== null
            || $credentialRows[1]['status'] !== 'active') {
            throw new RuntimeException('Credential replacement did not revoke and clear the prior live ciphertext.');
        }
        $newPlain = $vault->decrypt($websiteId, $credentialRows[1]);
        if (($newPlain['username'] ?? null) !== 'new-demo-operator' || ($newPlain['password'] ?? null) !== 'replacement-secret') {
            throw new RuntimeException('Replacement credential did not decrypt.');
        }
        sodium_memzero($newPlain['password']);
        sodium_memzero($newPlain['username']);

        $audit = $pdo->query('SELECT event_code, details_json FROM devpilot_security_audit WHERE website_id = ' . (int) $websiteId)->fetchAll(PDO::FETCH_ASSOC);
        $auditJson = json_encode($audit);
        if (!is_string($auditJson) || str_contains($auditJson, $secret) || str_contains($auditJson, 'replacement-secret')) {
            throw new RuntimeException('A credential value was written to audit history.');
        }
        if (count($audit) < 3) {
            throw new RuntimeException('Expected registration, scope, and credential audit events.');
        }

        $service->revokeCredential($websiteId, 1);
        $activeCount = (int) $pdo->query('SELECT COUNT(*) FROM devpilot_website_credentials WHERE website_id = ' . (int) $websiteId . " AND status = 'active'")->fetchColumn();
        if ($activeCount !== 0) {
            throw new RuntimeException('Credential revocation left an active credential.');
        }

        $service->revokeWebsite($websiteId, 1);
        $detail = $service->detail($websiteId, 1);
        if ($detail['website']['status'] !== 'revoked') {
            throw new RuntimeException('Website revocation did not persist.');
        }
        $activeScopes = array_filter($detail['scopes'], static fn(array $grant): bool => $grant['revoked_at'] === null);
        if ($activeScopes !== []) {
            throw new RuntimeException('Website revocation left active permission grants.');
        }

        try {
            $statement = $pdo->prepare("INSERT INTO devpilot_website_scopes (website_id, permission_code, constraints_json, granted_by) VALUES (999999, 'inspect_dashboard', '{}', 1)");
            $statement->execute();
            throw new RuntimeException('Foreign-key integrity accepted an unknown website.');
        } catch (PDOException) {
            // Expected.
        }

        $reauthorizedId = $service->register([
            'name' => 'Reauthorized demo website',
            'admin_url' => 'https://no-outbound-check.example.org/admin',
            'environment' => 'demo',
            'permissions' => [],
            'credentials' => ['username' => 'fresh-operator', 'password' => 'fresh-secret'],
        ], 1);
        if ($reauthorizedId === $websiteId || $service->detail($reauthorizedId, 1)['website']['status'] !== 'active') {
            throw new RuntimeException('A revoked origin could not be registered again with a new identity.');
        }
    }

    private function freshFixture(): array
    {
        if (getenv('DEVPILOT_TEST_MODE') !== '1') {
            throw new RuntimeException('Set DEVPILOT_TEST_MODE=1 to run persistence tests.');
        }
        $dsn = getenv('DEVPILOT_TEST_DSN');
        if (!is_string($dsn) || !preg_match('/(?:^|;)dbname=devpilot_test_[A-Za-z0-9_]+(?:;|$)/', $dsn)) {
            throw new RuntimeException('DEVPILOT_TEST_DSN must target a database named devpilot_test_*.');
        }
        $pdo = new PDO($dsn, (string) getenv('DEVPILOT_TEST_USER'), (string) getenv('DEVPILOT_TEST_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        foreach (['devpilot_website_credentials', 'devpilot_website_scopes', 'devpilot_security_audit', 'devpilot_websites', 'users'] as $table) {
            $pdo->exec('DELETE FROM `' . $table . '`');
        }
        $insertUser = $pdo->prepare('INSERT INTO users (id, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)');
        $insertUser->execute([1, 'test-admin', 'test-admin@invalid.example', password_hash('not-a-production-password', PASSWORD_DEFAULT), 'admin']);
        $insertUser->execute([2, 'test-user', 'test-user@invalid.example', password_hash('not-a-production-password', PASSWORD_DEFAULT), 'user']);

        $masterKey = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $vault = new CredentialVault(new VaultKeyring('test-master-v1', ['test-master-v1' => $masterKey]));
        return [$pdo, WebsiteServices::registration($pdo, $vault), $vault];
    }

    private function expectFailure(callable $operation): void
    {
        try {
            $operation();
        } catch (RuntimeException) {
            return;
        }
        throw new RuntimeException('An unauthorized website access unexpectedly succeeded.');
    }
}
