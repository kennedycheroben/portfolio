<?php
declare(strict_types=1);

namespace DevPilot\Tests;

use DevPilot\Connector\ArrayConnectorStore;
use DevPilot\Connector\ConnectorPolicy;
use DevPilot\Connector\ProjectConnectorService;
use DevPilot\Adapters\Php\ConnectorClient;
use RuntimeException;

final class ProjectConnectorTest
{
    public function testOneTimePairingAuthorizationInspectionAndReplayProtection(): void
    {
        $service = new ProjectConnectorService($store = new ArrayConnectorStore());
        $pairing = $service->beginPairing('portfolio-prod-1','Portfolio','https://portfolio.example.org',['project.inspect.read'],1000);
        $client = new ConnectorClient('portfolio-prod-1', 'Portfolio', 'https://portfolio.example.org');
        $authorized = $client->authorize($service, $pairing['pairing_code'], ['project.inspect.read'], 1001);
        $inspection = $client->inspect($service, $authorized['access_token'], 1002);
        if ($inspection['project_id'] !== 'portfolio-prod-1' || $inspection['origin'] !== 'https://portfolio.example.org' || !in_array(ConnectorPolicy::INSPECT,$inspection['scopes'],true)) throw new RuntimeException('Authenticated project inspection failed.');
        try { $service->authorize($pairing['pairing_code'],'portfolio-prod-1','Portfolio','https://portfolio.example.org',['project.inspect.read'],1003); throw new RuntimeException('Pairing code replay succeeded.'); } catch (RuntimeException $e) { if ($e->getMessage() === 'Pairing code replay succeeded.') throw $e; }
        foreach ($store->connections as $connection) if (isset($connection['token_hash']) && $connection['token_hash'] === $authorized['access_token']) throw new RuntimeException('Raw token was stored.');
    }
    public function testScopeEnforcementRevocationAndRotation(): void
    {
        $service = new ProjectConnectorService($store = new ArrayConnectorStore());
        $pairing = $service->beginPairing('p','Project','https://project.example.org',[ConnectorPolicy::INSPECT],10);
        $auth = $service->authorize($pairing['pairing_code'],'p','Project','https://project.example.org',[ConnectorPolicy::INSPECT],11);
        $service->revoke($auth['access_token'],12);
        try { $service->inspect($auth['access_token'],13); throw new RuntimeException('Revoked token was accepted.'); } catch (RuntimeException $e) { if ($e->getMessage() === 'Revoked token was accepted.') throw $e; }
        try { $service->beginPairing('p','Project','https://127.0.0.1',[ConnectorPolicy::INSPECT],1); throw new RuntimeException('Private origin was accepted.'); } catch (\Throwable $e) { if ($e->getMessage() === 'Private origin was accepted.') throw $e; }
    }

    public function testExpiredPairingCrossProjectAndUnauthorizedScopeAreDenied(): void
    {
        $service = new ProjectConnectorService(new ArrayConnectorStore());
        $expired = $service->beginPairing('owner-project','Project','https://project.example.org',[ConnectorPolicy::INSPECT],100);
        try { $service->authorize($expired['pairing_code'],'owner-project','Project','https://project.example.org',[ConnectorPolicy::INSPECT],401); throw new RuntimeException('Expired pairing was accepted.'); }
        catch (RuntimeException $e) { if ($e->getMessage() === 'Expired pairing was accepted.') throw $e; }

        $wrongIdentity = $service->beginPairing('owner-project','Project','https://project.example.org',[ConnectorPolicy::INSPECT],500);
        try { $service->authorize($wrongIdentity['pairing_code'],'another-project','Project','https://project.example.org',[ConnectorPolicy::INSPECT],501); throw new RuntimeException('Cross-project pairing was accepted.'); }
        catch (RuntimeException $e) { if ($e->getMessage() === 'Cross-project pairing was accepted.') throw $e; }

        $noScope = $service->beginPairing('owner-project','Project','https://project.example.org',[],600);
        try { $service->authorize($noScope['pairing_code'],'owner-project','Project','https://project.example.org',[ConnectorPolicy::INSPECT],601); throw new RuntimeException('Unauthorized scope was granted.'); }
        catch (RuntimeException $e) { if ($e->getMessage() === 'Unauthorized scope was granted.') throw $e; }
    }

    public function testRotationInvalidatesOldTokenAndInspectionIsSanitized(): void
    {
        $service = new ProjectConnectorService($store = new ArrayConnectorStore());
        $pairing = $service->beginPairing('p','Project','https://project.example.org',[ConnectorPolicy::INSPECT],1000);
        $old = $service->authorize($pairing['pairing_code'],'p','Project','https://project.example.org',[ConnectorPolicy::INSPECT],1001);
        $rotated = $service->rotate($old['access_token'],1002);
        try { $service->inspect($old['access_token'],1003); throw new RuntimeException('Rotated token remained valid.'); }
        catch (RuntimeException $e) { if ($e->getMessage() === 'Rotated token remained valid.') throw $e; }
        $inspection = $service->inspect($rotated['access_token'],1004);
        foreach (['password','secret','api_key','session_token','database_url'] as $forbidden) if (array_key_exists($forbidden,$inspection)) throw new RuntimeException('Inspection disclosed a sensitive field.');
        if (($inspection['connection_health'] ?? '') !== 'healthy' || !isset($inspection['inspected_at'])) throw new RuntimeException('Inspection overview is incomplete.');
        foreach ($store->connections as $connection) if (isset($connection['token_hash']) && hash_equals($connection['token_hash'], $rotated['access_token'])) throw new RuntimeException('Bearer token was stored in plaintext.');
    }

    public function testOriginAndResolvedAddressPolicyRejectUnsafeTargets(): void
    {
        foreach (['https://example.org/path','https://example.org?next=x','https://localhost','https://service.localhost','https://127.0.0.1','https://internal'] as $origin) {
            try { ConnectorPolicy::origin($origin); throw new RuntimeException('Unsafe origin was accepted: ' . $origin); }
            catch (\InvalidArgumentException $e) { if (str_starts_with($e->getMessage(), 'Unsafe origin was accepted:')) throw $e; }
        }
        foreach (['127.0.0.1','10.0.0.1','169.254.1.1','192.0.2.1','::1','fc00::1','fe80::1'] as $ip) {
            try { ConnectorPolicy::publicAddresses($ip); throw new RuntimeException('Prohibited network address was accepted: ' . $ip); }
            catch (\InvalidArgumentException $e) { if (str_starts_with($e->getMessage(), 'Prohibited network address was accepted:')) throw $e; }
        }
        try { (new \DevPilot\Connector\ConnectorHttpClient())->post('https://example.org','/api/devpilot-connector.php?action=redirect'); throw new RuntimeException('Unapproved connector path was accepted.'); }
        catch (\RuntimeException $e) { if ($e->getMessage() === 'Unapproved connector path was accepted.') throw $e; }
    }

    public function testConnectionCannotBeUsedAgainstAnotherProjectIdentity(): void
    {
        $service = new ProjectConnectorService(new ArrayConnectorStore());
        $pairing = $service->beginPairing('project-a','Project A','https://a.example.org',[ConnectorPolicy::INSPECT],100);
        $authorization = $service->authorize($pairing['pairing_code'],'project-a','Project A','https://a.example.org',[ConnectorPolicy::INSPECT],101);
        try { $service->inspectForProject($authorization['access_token'],'project-b','https://b.example.org','PHP',102); throw new RuntimeException('Cross-project inspection succeeded.'); }
        catch (RuntimeException $e) { if ($e->getMessage() === 'Cross-project inspection succeeded.') throw $e; }
        try { $service->revokeForProject($authorization['access_token'],'project-b','https://b.example.org',103); throw new RuntimeException('Cross-project revocation succeeded.'); }
        catch (RuntimeException $e) { if ($e->getMessage() === 'Cross-project revocation succeeded.') throw $e; }
        $service->inspectForProject($authorization['access_token'],'project-a','https://a.example.org','PHP',104);
    }

    public function testDashboardAndRegistryKeepAdminCsrfAndOwnerBoundState(): void
    {
        $root = dirname(__DIR__, 2);
        $adminApi = file_get_contents($root . '/admin/api/devpilot-connector.php');
        $dashboard = file_get_contents($root . '/admin/devpilot-websites.php');
        $registry = file_get_contents(__DIR__ . '/../src/Connector/ConnectedProjectStore.php');
        $client = file_get_contents(__DIR__ . '/../adapters/php/ConnectorClient.php');
        if (!is_string($adminApi) || !is_string($dashboard) || !is_string($registry) || !is_string($client)) throw new RuntimeException('Connector integration files are missing.');
        $adminGate = strpos($adminApi, 'requireAdmin();');
        $csrfGate = strpos($adminApi, 'verify_csrf();');
        if ($adminGate === false || $csrfGate === false || $adminGate > $csrfGate) throw new RuntimeException('Dashboard mutations are missing admin or CSRF enforcement.');
        foreach (['Connect DevPilot','Refresh inspection','Disconnect DevPilot'] as $action) if (!str_contains($dashboard, $action)) throw new RuntimeException('Dashboard action is missing: ' . $action);
        if (!str_contains($registry, 'WHERE owner_id=? AND project_id=?') || !str_contains($registry, '$this->vault->encrypt(')) throw new RuntimeException('DevPilot registry is not owner-scoped or credential-encrypted.');
        if (!str_contains($client, 'inspectForProject') || !str_contains($client, 'revokeForProject')) throw new RuntimeException('PHP client does not bind operations to project identity.');
    }
}
