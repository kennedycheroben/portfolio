<?php
declare(strict_types=1);

namespace DevPilot\Website;

use PDO;

final class WebsiteServices
{
    public static function registration(PDO $pdo, ?CredentialVault $vault = null): WebsiteRegistrationService
    {
        $repository = new WebsiteRepository($pdo);
        $scopePolicy = new WebsiteScopePolicy();
        return new WebsiteRegistrationService(
            $pdo,
            $repository,
            new WebsiteUrlPolicy(),
            $scopePolicy,
            $vault ?? new CredentialVault(),
            new WebsiteAuditLogger($repository),
            new WebsiteAuth($repository)
        );
    }

    public static function repository(PDO $pdo): WebsiteRepository
    {
        return new WebsiteRepository($pdo);
    }
}
