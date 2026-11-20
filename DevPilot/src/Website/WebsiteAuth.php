<?php
declare(strict_types=1);

namespace DevPilot\Website;

use RuntimeException;

/** Retains the existing global admin gate and adds owner-filtered site access. */
final class WebsiteAuth
{
    public static function currentAdminId(): int
    {
        $userId = $_SESSION['user_id'] ?? null;
        $role = $_SESSION['user_role'] ?? null;
        if (!is_numeric($userId) || (int) $userId <= 0 || !is_string($role) || !hash_equals('admin', $role)) {
            throw new RuntimeException('Administrator authentication is required.');
        }
        return (int) $userId;
    }

    public function __construct(private readonly WebsiteRepository $repository)
    {
    }

    public function requireOwned(int $websiteId, int $ownerId, bool $active = false, bool $lock = false): array
    {
        $website = $this->repository->findOwned($websiteId, $ownerId, $lock);
        if ($website === null) {
            throw new RuntimeException('Website was not found.');
        }
        if ($active && $website['status'] !== 'active') {
            throw new RuntimeException('This website registration has been revoked.');
        }
        return $website;
    }
}
