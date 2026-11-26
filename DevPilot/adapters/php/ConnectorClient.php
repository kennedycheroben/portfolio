<?php
declare(strict_types=1);

namespace DevPilot\Adapters\Php;

use RuntimeException;

/** Reference client: the host application supplies its own server-side identity and origin. */
final class ConnectorClient
{
    public function __construct(private readonly string $projectId, private readonly string $projectName, private readonly string $origin) {}
    public function identity(): array { return ['project_id'=>$this->projectId,'project_name'=>$this->projectName,'origin'=>$this->origin]; }
    public function authorize(\DevPilot\Connector\ProjectConnectorService $service, string $pairingCode, array $scopes, ?int $now = null): array { return $service->authorize($pairingCode,$this->projectId,$this->projectName,$this->origin,$scopes,$now ?? time()); }
    public function inspect(\DevPilot\Connector\ProjectConnectorService $service, string $token, ?int $now = null): array { return $service->inspect($token,$now ?? time()); }
}
