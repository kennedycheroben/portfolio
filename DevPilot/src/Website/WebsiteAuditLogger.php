<?php
declare(strict_types=1);

namespace DevPilot\Website;

use InvalidArgumentException;

final class WebsiteAuditLogger
{
    public const EVENTS = [
        'website_registered',
        'scope_replaced',
        'credential_replaced',
        'credential_revoked',
        'website_revoked',
        'website_access_denied',
    ];

    public function __construct(private readonly WebsiteRepository $repository)
    {
    }

    public function record(int $actorId, ?int $websiteId, string $event, string $outcome, array $details = []): void
    {
        if (!in_array($event, self::EVENTS, true) || !in_array($outcome, ['success', 'denied', 'failure'], true)) {
            throw new InvalidArgumentException('Audit event is invalid.');
        }
        $safeDetails = [];
        foreach (['permission_codes', 'credential_version', 'reason_code'] as $key) {
            if (array_key_exists($key, $details)) {
                $safeDetails[$key] = $details[$key];
            }
        }
        $this->repository->audit($actorId, $websiteId, $event, $outcome, $safeDetails);
    }
}
