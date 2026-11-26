<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/devpilot-loader.php';
devpilot_register_autoloader();

use DevPilot\Connector\ProjectConnectorService;
use DevPilot\Adapters\Php\ConnectorClient;
use DevPilot\Website\WebsiteServices;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$pdo instanceof PDO) {
    http_response_code($_SERVER['REQUEST_METHOD'] === 'POST' ? 503 : 405);
    echo json_encode(['success'=>false,'error'=>'Connector unavailable']);
    exit;
}
$rawBody = file_get_contents('php://input', false, null, 0, 8193);
if (!is_string($rawBody) || strlen($rawBody) > 8192) { http_response_code(413); echo json_encode(['success'=>false,'error'=>'Request is too large']); exit; }
$input = json_decode($rawBody, true);
if (!is_array($input)) $input = $_POST;
$action = (string) ($_GET['action'] ?? '');
$service = WebsiteServices::connector($pdo);
$origin = rtrim((string) (getenv('APP_URL') ?: ''), '/');
$projectId = 'php-' . substr(hash('sha256', $origin . '|' . dirname(__DIR__)), 0, 24);
$projectName = 'Portfolio project';
$client = new ConnectorClient($projectId, $projectName, $origin);
try {
    if ($origin === '') throw new RuntimeException('Project origin is unavailable.');
    if ($action === 'authorize') {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!$service->pairingAllowed($remote, time()) || !is_string($input['pairing_code'] ?? null)) throw new RuntimeException('Pairing authorization failed.');
        try { $authorized = $client->authorize($service, $input['pairing_code'], ['project.inspect.read'], time()); }
        catch (Throwable $exception) { $service->recordPairingFailure($remote, time()); throw $exception; }
        echo json_encode(['success'=>true,'connection'=>$authorized], JSON_THROW_ON_ERROR);
        exit;
    }
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer (dvp_[A-Za-z0-9_-]{40,})$/', $header, $match)) throw new RuntimeException('Connector authentication failed.');
    if ($action === 'inspect') {
        $root = dirname(__DIR__);
        $framework = 'Vanilla PHP';
        if (is_file($root . '/artisan')) {
            $framework = 'Laravel';
        } elseif (is_file($root . '/composer.json')) {
            $composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
            $requirements = is_array($composer) ? array_merge((array) ($composer['require'] ?? []), (array) ($composer['require-dev'] ?? [])) : [];
            if (isset($requirements['symfony/framework-bundle'])) $framework = 'Symfony';
            elseif (isset($requirements['slim/slim'])) $framework = 'Slim';
        }
        $inspection = $client->inspect($service, $match[1], time(), $framework);
        echo json_encode(['success'=>true,'inspection'=>$inspection], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($action === 'revoke') {
        $client->revoke($service, $match[1], time());
        echo json_encode(['success'=>true]);
        exit;
    }
    http_response_code(404);
    echo json_encode(['success'=>false,'error'=>'Unknown connector operation']);
} catch (Throwable $exception) {
    error_log('DevPilot project connector failed: ' . get_class($exception));
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Connector authorization failed.']);
}
