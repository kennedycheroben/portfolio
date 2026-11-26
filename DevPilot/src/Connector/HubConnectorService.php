<?php
declare(strict_types=1);

namespace DevPilot\Connector;

use RuntimeException;

/** Hub-only pairing approval, registration, inspection refresh, and revocation workflow. */
final class HubConnectorService
{
    public function __construct(
        private readonly HubPairingRepository $pairings,
        private readonly ConnectedProjectStore $projects,
        private readonly ConnectorHttpClient $http = new ConnectorHttpClient(),
    ) {}

    public function approve(string $requestId, int $ownerId, int $now): array
    {
        $request = $this->pairings->claim($requestId,$ownerId,$now);
        if ($request === null) throw new RuntimeException('Pairing request is expired or already being processed.');
        $token = null;
        $registered = false;
        try {
            $pairingCode = $this->pairings->decryptCode($request);
            if (!hash_equals($request['pairing_code_hash'],hash('sha256',$pairingCode,true))) throw new RuntimeException('Pairing request integrity check failed.');
            $authorization = $this->http->post($request['origin'],'/api/devpilot-connector.php?action=authorize',null,['pairing_code'=>$pairingCode]);
            unset($pairingCode);
            $connection = $authorization['connection'] ?? null;
            if (is_array($connection) && is_string($connection['access_token'] ?? null)) $token = $connection['access_token'];
            if (!is_array($connection) || !is_string($connection['access_token'] ?? null)
                || !hash_equals($request['project_id'],(string)($connection['project_id'] ?? ''))
                || !hash_equals($request['origin'],(string)($connection['origin'] ?? ''))
                || !in_array(ConnectorPolicy::INSPECT,(array)($connection['scopes'] ?? []),true)) {
                throw new RuntimeException('Project authorization response could not be verified.');
            }
            $inspectionResponse = $this->http->post($request['origin'],'/api/devpilot-connector.php?action=inspect',$token);
            $inspection = $inspectionResponse['inspection'] ?? null;
            if (!is_array($inspection) || !hash_equals($request['project_id'],(string)($inspection['project_id'] ?? '')) || !hash_equals($request['origin'],(string)($inspection['origin'] ?? ''))) {
                throw new RuntimeException('Authenticated project inspection could not be verified.');
            }
            $this->projects->save($ownerId,$connection,$token,$inspection,$now);
            $registered = true;
            $this->pairings->complete($requestId,$now);
            return ['project'=>$this->projects->status($ownerId,$request['project_id']),'return_url'=>$request['return_url']];
        } catch (\Throwable $exception) {
            if (is_string($token)) {
                try { $this->http->post($request['origin'],'/api/devpilot-connector.php?action=revoke',$token); } catch (\Throwable) {}
            }
            if ($registered) {
                try { $this->projects->disconnect($ownerId,$request['project_id'],$now); } catch (\Throwable) {}
            }
            try { $this->pairings->fail($requestId,$now); } catch (\Throwable) {}
            throw $exception;
        }
    }

    public function refresh(int $ownerId, string $projectId, int $now): array
    {
        $row = $this->projects->status($ownerId,$projectId);
        if ($row === null || $row['status'] !== 'active') throw new RuntimeException('Project connection is unavailable.');
        try {
            $token = $this->projects->token($ownerId,$projectId);
            $response = $this->http->post($row['origin'],'/api/devpilot-connector.php?action=inspect',$token);
            $inspection = $response['inspection'] ?? null;
            if (!is_array($inspection) || !hash_equals($projectId,(string)($inspection['project_id'] ?? '')) || !hash_equals($row['origin'],(string)($inspection['origin'] ?? ''))) throw new RuntimeException('Authenticated project inspection could not be verified.');
            $this->projects->updateInspection($ownerId,$projectId,$inspection,$now);
            return $this->projects->status($ownerId,$projectId) ?? throw new RuntimeException('Project connection is unavailable.');
        } catch (\Throwable $exception) {
            $this->projects->recordInspectionFailure($ownerId,$projectId,$now);
            throw $exception;
        }
    }

    public function disconnect(int $ownerId, string $projectId, int $now): void
    {
        $row = $this->projects->status($ownerId,$projectId);
        if ($row === null || $row['status'] !== 'active') throw new RuntimeException('Project connection is unavailable.');
        $token = $this->projects->token($ownerId,$projectId);
        $this->http->post($row['origin'],'/api/devpilot-connector.php?action=revoke',$token);
        $this->projects->disconnect($ownerId,$projectId,$now);
    }
}
