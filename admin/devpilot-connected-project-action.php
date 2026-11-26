<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/devpilot-loader.php';
devpilot_register_autoloader();

use DevPilot\Connector\ConnectorPolicy;
use DevPilot\Website\WebsiteAuth;
use DevPilot\Website\WebsiteServices;

if (!$pdo instanceof PDO) { http_response_code(503); exit('Hub database unavailable.'); }
$ownerId = WebsiteAuth::currentAdminId();
$projectId = (string) ($_GET['project_id'] ?? $_POST['project_id'] ?? '');
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
if (!preg_match('/^[A-Za-z0-9._:-]{1,128}$/',$projectId) || !in_array($action,['refresh','disconnect'],true)) { http_response_code(400); exit('Project action is invalid.'); }
$projects = WebsiteServices::connectedProjects($pdo);
$project = $projects->status($ownerId,$projectId);
if ($project === null || $project['status'] !== 'active') { http_response_code(404); exit('Connected project was not found.'); }
$returnUrl = $project['origin'] . '/admin/devpilot-websites.php';
$appEnvironment = (string) getenv('APP_ENV');
$connectorEnabled = in_array($appEnvironment,['development','local','staging'],true) || ($appEnvironment==='production' && getenv('DEVPILOT_CONNECTOR_SECURITY_VERIFIED')==='1');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if ($action === 'refresh' && !$connectorEnabled) throw new RuntimeException('Hub project operations are disabled until the security review is complete.');
        if ($action === 'refresh') WebsiteServices::hubConnector($pdo)->refresh($ownerId,$projectId,time());
        else WebsiteServices::hubConnector($pdo)->disconnect($ownerId,$projectId,time());
        header('Location: ' . $returnUrl . '?connector_action=' . ($action === 'refresh' ? 'refreshed' : 'disconnected'), true, 303);
        exit;
    } catch (Throwable $exception) {
        error_log('DevPilot project action confirmation failed: ' . get_class($exception));
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Project action failed.';
    }
}
$adminPageTitle = $action === 'refresh' ? 'Refresh Project Inspection' : 'Disconnect Project';
$adminActiveNav = 'devpilot-websites';
$unreadCount = 0;
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1><?= e($adminPageTitle) ?></h1><p class="text-muted mb-0"><?= e((string) $project['project_name']) ?> · <?= e((string) $project['origin']) ?></p></div><a class="btn-secondary" href="devpilot-connected-projects.php">Back to connected projects</a></div>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="card"><div class="card-body"><p><?= $action === 'refresh' ? 'DevPilot will make an authenticated, read-only inspection request and save the sanitized result.' : 'DevPilot will revoke its bearer authorization at the customer project and mark this connection revoked.' ?></p><form method="post"><?= csrf_field() ?><input type="hidden" name="project_id" value="<?= e($projectId) ?>"><input type="hidden" name="action" value="<?= e($action) ?>"><button class="btn-accent" type="submit" <?= $action === 'refresh' && !$connectorEnabled ? 'disabled' : '' ?>><?= $action === 'refresh' ? 'Refresh inspection' : 'Revoke and disconnect' ?></button></form></div></section></main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
