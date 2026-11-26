<?php
declare(strict_types=1);

namespace DevPilot\Admin\WebsitesApi;

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/devpilot-loader.php';
devpilot_register_autoloader();

use DevPilot\Website\WebsiteAuth;
use DevPilot\Website\WebsiteServices;
use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;

function devpilotWebsiteRespond(array $body, int $status = 200): never
{
    foreach (['payload', '_POST'] as $globalName) {
        if (!isset($GLOBALS[$globalName]) || !is_array($GLOBALS[$globalName])) {
            continue;
        }
        foreach (['username', 'password'] as $secretField) {
            if (isset($GLOBALS[$globalName][$secretField]) && is_string($GLOBALS[$globalName][$secretField])) {
                if (function_exists('sodium_memzero')) {
                    sodium_memzero($GLOBALS[$globalName][$secretField]);
                }
                unset($GLOBALS[$globalName][$secretField]);
            }
        }
    }
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$websiteId = 0;
if (array_key_exists('id', $_GET)) {
    $validatedId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($validatedId === false || $validatedId <= 0) {
        devpilotWebsiteRespond(['error' => 'Website ID is invalid.'], 400);
    }
    $websiteId = $validatedId;
}
$resource = is_string($_GET['resource'] ?? null) ? $_GET['resource'] : '';
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 16384) {
    devpilotWebsiteRespond(['error' => 'Request is too large.'], 413);
}

$payload = [];
if ($method === 'POST') {
    $payload = $_POST;
} elseif (in_array($method, ['PUT', 'DELETE'], true)) {
    $rawBody = file_get_contents('php://input', false, null, 0, 16385);
    if (!is_string($rawBody) || strlen($rawBody) > 16384) {
        devpilotWebsiteRespond(['error' => 'Request is too large.'], 413);
    }
    if ($method === 'PUT' && strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0])) !== 'application/json') {
        devpilotWebsiteRespond(['error' => 'Use a JSON request body.'], 415);
    }
    if ($rawBody !== '') {
        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            devpilotWebsiteRespond(['error' => 'Invalid request body.'], 400);
        }
        $payload = $decoded;
    }
}

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    $csrf = $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($csrf) || $csrf === '' || !hash_equals(csrf_token(), $csrf)) {
        devpilotWebsiteRespond(['error' => 'Your session expired. Refresh the page and try again.'], 419);
    }
}

if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');
    devpilotWebsiteRespond(['error' => 'Method is not supported.'], 405);
}
if (!$pdo instanceof PDO) {
    devpilotWebsiteRespond(['error' => 'Website management is temporarily unavailable.'], 503);
}

try {
    $service = WebsiteServices::registration($pdo);
    $ownerId = WebsiteAuth::currentAdminId();
    if ($method === 'GET') {
        if ($websiteId > 0) {
            $result = $service->detail($websiteId, $ownerId);
        } elseif ($websiteId === 0) {
            $result = ['websites' => $service->list($ownerId)];
        } else {
            throw new InvalidArgumentException('Website ID is invalid.');
        }
        devpilotWebsiteRespond($result);
    }

    if ($method === 'POST' && $websiteId === 0 && ($payload['action'] ?? 'register') === 'register') {
        $id = $service->register([
            'name' => $payload['name'] ?? null,
            'admin_url' => $payload['admin_url'] ?? null,
            'environment' => $payload['environment'] ?? 'unknown',
            'mfa_expected' => $payload['mfa_expected'] ?? false,
            'permissions' => $payload['permissions'] ?? [],
            'credentials' => [
                'username' => $payload['username'] ?? null,
                'password' => $payload['password'] ?? null,
            ],
        ], $ownerId);
        devpilotWebsiteRespond(['success' => true, 'id' => $id], 201);
    }

    if ($method === 'PUT' && $websiteId > 0 && $resource === 'scope') {
        if (!is_array($payload['permissions'] ?? null)) {
            throw new InvalidArgumentException('Permissions must be a list.');
        }
        $service->replaceScopes($websiteId, $ownerId, $payload['permissions']);
        devpilotWebsiteRespond(['success' => true]);
    }
    if ($method === 'PUT' && $websiteId > 0 && $resource === 'credential') {
        $service->replaceCredential($websiteId, $ownerId, [
            'username' => $payload['username'] ?? null,
            'password' => $payload['password'] ?? null,
        ]);
        devpilotWebsiteRespond(['success' => true]);
    }
    if ($method === 'DELETE' && $websiteId > 0 && $resource === 'credential') {
        $service->revokeCredential($websiteId, $ownerId);
        devpilotWebsiteRespond(['success' => true]);
    }
    if ($method === 'DELETE' && $websiteId > 0 && $resource === 'website') {
        $service->revokeWebsite($websiteId, $ownerId);
        devpilotWebsiteRespond(['success' => true]);
    }

    header('Allow: GET, POST, PUT, DELETE');
    devpilotWebsiteRespond(['error' => 'Method or route is not supported.'], 405);
} catch (InvalidArgumentException $exception) {
    devpilotWebsiteRespond(['error' => $exception->getMessage()], 422);
} catch (PDOException $exception) {
    error_log('DevPilot website request failed: database error');
    devpilotWebsiteRespond(['error' => 'Website management is temporarily unavailable.'], 503);
} catch (Throwable $exception) {
    // Do not log exception messages or request values; they may contain user input.
    error_log('DevPilot website request failed: ' . get_class($exception));
    $message = $exception->getMessage();
    $safeMessages = [
        'Website was not found.',
        'This website registration has been revoked.',
        'Credential is unavailable.',
        'Credential integrity check failed.',
        'Credential key version is unavailable.',
        'Secure credential storage is unavailable.',
    ];
    $status = match ($message) {
        'Website was not found.' => 404,
        'Secure credential storage is unavailable.' => 503,
        default => 409,
    };
    devpilotWebsiteRespond(['error' => in_array($message, $safeMessages, true) ? $message : 'The request could not be completed.'], $status);
}
