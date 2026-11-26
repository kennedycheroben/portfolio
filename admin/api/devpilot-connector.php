<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../DevPilot/autoload.php';

use DevPilot\Connector\ConnectorPolicy;
use DevPilot\Website\WebsiteAuth;
use DevPilot\Website\WebsiteServices;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!$pdo instanceof PDO) { http_response_code(503); echo json_encode(['error' => 'Connector unavailable']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
verify_csrf();

try {
    if (getenv('APP_ENV') === 'production' && getenv('DEVPILOT_CONNECTOR_SECURITY_VERIFIED') !== '1') {
        throw new RuntimeException('Production connector is disabled until independently verified.');
    }
    $origin = rtrim((string) (getenv('APP_URL') ?: ''), '/');
    if ($origin === '') throw new InvalidArgumentException('APP_URL must be configured before connecting a project.');
    $origin = ConnectorPolicy::origin($origin);
    $identity = hash('sha256', $origin . '|' . dirname(__DIR__, 2), false);
    $service = WebsiteServices::connector($pdo);
    $pairing = $service->beginPairing('php-' . substr($identity, 0, 24), 'Portfolio project', $origin, [ConnectorPolicy::INSPECT], time());
    // The project connector consumes this one-time code server-side. Only the
    // short-lived pairing code is returned; the bearer token is never exposed.
    $client = new \DevPilot\Adapters\Php\ConnectorClient($pairing['project_id'], 'Portfolio project', $origin);
    $authorized = $client->authorize($service, $pairing['pairing_code'], [ConnectorPolicy::INSPECT]);
    unset($authorized['access_token']);
    $authorized['status'] = $service->status($pairing['project_id']);
    echo json_encode(['success' => true, 'connection' => $authorized], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('DevPilot connector request failed: ' . get_class($exception));
    http_response_code($exception instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['error' => 'The project could not be connected.']);
}
