<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/devpilot-loader.php';
devpilot_register_autoloader();

use DevPilot\Website\WebsiteAuth;
use DevPilot\Website\WebsiteServices;

if (!$pdo instanceof PDO) { http_response_code(503); exit('Hub database unavailable.'); }
$ownerId = WebsiteAuth::currentAdminId();
$requestId = (string) ($_GET['request'] ?? $_POST['request_id'] ?? '');
$error = '';
$success = '';
$pairings = WebsiteServices::hubPairings($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'approve') {
            $result = WebsiteServices::hubConnector($pdo)->approve($requestId,$ownerId,time());
            $returnUrl = (string) $result['return_url'];
            header('Location: ' . $returnUrl . '?connector=connected', true, 303);
            exit;
        }
        if ($action === 'reject') { $pairings->reject($requestId,time()); $success = 'The project request was rejected.'; }
        else { throw new RuntimeException('Unknown pairing action.'); }
    } catch (Throwable $exception) {
        error_log('DevPilot Hub approval failed: ' . get_class($exception));
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'The project could not be connected.';
    }
}
try { $request = $pairings->pending($requestId,time()); }
catch (Throwable) { $request = null; if ($error === '' && $success === '') $error = 'This pairing request is expired, already handled, or unavailable.'; }

$adminPageTitle = 'Approve DevPilot Project';
$adminActiveNav = 'devpilot-websites';
$unreadCount = 0;
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3"><div><h1 class="mb-1">Approve project connection</h1><p class="text-muted mb-0">Review the project identity and read-only permissions before the Hub connects.</p></div><a class="btn-secondary" href="devpilot-connected-projects.php">Connected projects</a></div>
  <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
  <?php if (is_array($request)): ?>
    <section class="card"><div class="card-header"><strong><?= e((string) $request['project_name']) ?></strong></div><div class="card-body">
      <dl class="row"><dt class="col-sm-3">Project ID</dt><dd class="col-sm-9"><?= e((string) $request['project_id']) ?></dd><dt class="col-sm-3">Canonical origin</dt><dd class="col-sm-9"><?= e((string) $request['origin']) ?></dd><dt class="col-sm-3">Requested permissions</dt><dd class="col-sm-9"><?= e(implode(', ', (array) $request['scopes'])) ?></dd><dt class="col-sm-3">Expires</dt><dd class="col-sm-9"><?= e((string) $request['expires_at']) ?></dd></dl>
      <p>The Hub will verify the project over HTTPS, store its token encrypted, and perform an authenticated read-only inspection. Approving grants only the scopes listed above.</p>
      <div class="d-flex gap-2"><form method="post"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= e($requestId) ?>"><input type="hidden" name="action" value="approve"><button class="btn-accent" type="submit">Approve and inspect</button></form><form method="post"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= e($requestId) ?>"><input type="hidden" name="action" value="reject"><button class="btn-secondary" type="submit">Reject</button></form></div>
    </div></section>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
