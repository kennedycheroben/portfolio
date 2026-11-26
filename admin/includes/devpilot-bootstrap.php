<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/devpilot-loader.php';

/**
 * Load DevPilot without requiring Composer's vendor directory on shared hosting.
 * Call only after the portfolio authentication bootstrap and requireAdmin().
 */
function devpilot_initialize_runtime(?string $root = null): ?string
{
    try {
        $root ??= devpilot_root();
    } catch (\Throwable $error) {
        error_log('DevPilot root unavailable: ' . get_class($error));
        return 'required_files_missing';
    }
    foreach (['src/Config.php', 'src/Registry.php', 'api/DeviceAuth.php', 'api/OperationQueue.php'] as $requiredFile) {
        if (!is_file($root . '/' . $requiredFile) || !is_readable($root . '/' . $requiredFile)) {
            return 'required_files_missing';
        }
    }

    try {
        devpilot_register_autoloader();
        if (!extension_loaded('pdo')) {
            return 'pdo_extension_missing';
        }
        \DevPilot\Config::ensureDirectories();
        new \DevPilot\Registry(\DevPilot\Config::getRegistryPath());
        new \DevPilot\Api\OperationQueue();
        return null;
    } catch (\Throwable $error) {
        error_log('DevPilot initialization failed: ' . get_class($error));
        return 'runtime_storage_unavailable';
    }
}

function devpilot_unavailable(bool $json = false): never
{
    http_response_code(503);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['error' => 'DevPilot is temporarily unavailable. Contact the site administrator.']);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>DevPilot unavailable</title>'
        . '<main><h1>DevPilot is temporarily unavailable</h1><p>Contact the site administrator.</p></main></html>';
    exit;
}
