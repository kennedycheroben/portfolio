<?php
declare(strict_types=1);

namespace DevPilot\Website;

use InvalidArgumentException;

/** Phase 1 persists these explicit grants; no executor consumes them yet. */
final class WebsiteScopePolicy
{
    public const PERMISSIONS = [
        'inspect_dashboard' => 'Inspect dashboard',
        'read_account_roles' => 'Read account roles',
        'create_demo_accounts' => 'Create demo accounts',
        'create_demo_content' => 'Create fictional demo content',
        'update_owned_demo_records' => 'Update DevPilot-owned demo records',
        'upload_demo_assets' => 'Upload authorized demo assets',
        'cleanup_owned_demo_data' => 'Clean up DevPilot-owned demo data',
    ];

    /** @return list<string> */
    public function normalize(array $permissions): array
    {
        $normalized = [];
        foreach ($permissions as $permission) {
            if (!is_string($permission) || !isset(self::PERMISSIONS[$permission])) {
                throw new InvalidArgumentException('One or more selected permissions are not supported.');
            }
            $normalized[$permission] = $permission;
        }
        return array_values($normalized);
    }

    public function allows(array $activeGrants, string $permission): bool
    {
        if (!isset(self::PERMISSIONS[$permission])) {
            return false;
        }
        foreach ($activeGrants as $grant) {
            if (is_array($grant)
                && ($grant['permission_code'] ?? null) === $permission
                && ($grant['revoked_at'] ?? null) === null) {
                return true;
            }
        }
        return false;
    }
}
