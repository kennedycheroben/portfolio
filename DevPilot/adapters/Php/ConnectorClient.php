<?php
declare(strict_types=1);

namespace DevPilot\Adapters\Php;

use RuntimeException;

/** PHP project-side adapter; callers must build identity from server configuration, never request data. */
final class ConnectorClient
{
    public function __construct(private readonly string $projectId, private readonly string $projectName, private readonly string $origin) {}
    public function identity(): array { return ['project_id'=>$this->projectId,'project_name'=>$this->projectName,'origin'=>$this->origin]; }
    public function authorize(\DevPilot\Connector\ProjectConnectorService $service, string $pairingCode, array $scopes, ?int $now = null): array { return $service->authorize($pairingCode,$this->projectId,$this->projectName,$this->origin,$scopes,$now ?? time()); }
    public function inspect(\DevPilot\Connector\ProjectConnectorService $service, string $token, ?int $now = null, string $framework = 'PHP'): array { return $service->inspectForProject($token,$this->projectId,$this->origin,$framework,$now ?? time()); }
    public function revoke(\DevPilot\Connector\ProjectConnectorService $service, string $token, ?int $now = null): void { $service->revokeForProject($token,$this->projectId,$this->origin,$now ?? time()); }
}
