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
}
