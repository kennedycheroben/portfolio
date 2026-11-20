<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
$websiteId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($websiteId <= 0) {
    http_response_code(400);
    exit('Website ID is invalid.');
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../DevPilot/autoload.php';

use DevPilot\Website\WebsiteAuth;
use DevPilot\Website\WebsiteScopePolicy;
use DevPilot\Website\WebsiteServices;

if (!$pdo instanceof PDO) {
    http_response_code(503);
    exit('Website management is temporarily unavailable.');
}
$service = WebsiteServices::registration($pdo);
$adminId = WebsiteAuth::currentAdminId();
$error = '';
$success = isset($_GET['created']) ? 'Website authorization was saved.' : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input_string($_POST, 'action');
    try {
        if ($action === 'replace_scopes') {
            $permissions = $_POST['permissions'] ?? [];
            if (!is_array($permissions)) {
                throw new InvalidArgumentException('Permissions must be a list.');
            }
            $service->replaceScopes($websiteId, $adminId, $permissions);
            $success = 'Website permissions were updated.';
        } elseif ($action === 'replace_credential') {
            $service->replaceCredential($websiteId, $adminId, [
                'username' => input_string($_POST, 'username'),
                'password' => input_string($_POST, 'password', false),
            ]);
            $success = 'Website credential was replaced.';
        } elseif ($action === 'revoke_credential') {
            $service->revokeCredential($websiteId, $adminId);
            $success = 'Website credential was revoked.';
        } elseif ($action === 'revoke_website') {
            $service->revokeWebsite($websiteId, $adminId);
            $success = 'Website authorization and active credentials were revoked.';
        } else {
            throw new InvalidArgumentException('Select a supported website action.');
        }
        $_POST = [];
        header('Location: devpilot-website.php?id=' . $websiteId . '&updated=1', true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('DevPilot website management failed: ' . get_class($exception));
        $error = 'The website change could not be completed. Check the database and vault key configuration.';
    } finally {
        unset($_POST['password'], $_POST['username']);
    }
}
if (isset($_GET['updated'])) {
    $success = 'Website settings were updated.';
}
try {
    $detail = $service->detail($websiteId, $adminId);
} catch (Throwable $exception) {
    http_response_code(404);
    exit('Website was not found.');
}
$website = $detail['website'];
$activeScopes = [];
foreach ($detail['scopes'] as $grant) {
    if ($grant['revoked_at'] === null) {
        $activeScopes[] = $grant['permission_code'];
    }
}
$adminPageTitle = 'Manage Website';
$adminActiveNav = 'devpilot-websites';
$unreadCount = 0;
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3"><div><h1 class="mb-1"><?= e($website['name']) ?></h1><p class="text-muted mb-0"><?= e($website['admin_url']) ?></p></div><a class="btn-secondary" href="devpilot-websites.php">Back to websites</a></div>
  <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>

  <div class="row g-4">
    <div class="col-xl-6"><section class="card h-100"><div class="card-header"><strong>Authorization</strong></div><div class="card-body"><dl class="row"><dt class="col-sm-4">Environment</dt><dd class="col-sm-8"><?= e(ucfirst($website['environment'])) ?></dd><dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= e(ucfirst($website['status'])) ?></dd><dt class="col-sm-4">MFA</dt><dd class="col-sm-8"><?= $website['mfa_expected'] ? 'May be required; manual completion only' : 'Not declared' ?></dd><dt class="col-sm-4">Credential</dt><dd class="col-sm-8"><?= e((string) ($detail['credential']['status'] ?? 'not stored')) ?><?= isset($detail['credential']['credential_version']) ? ' · version ' . (int) $detail['credential']['credential_version'] : '' ?></dd></dl><p class="text-muted">Saved usernames and passwords are write-only and are never displayed.</p></div></section></div>
    <div class="col-xl-6"><section class="card h-100"><div class="card-header"><strong>Replace credential</strong></div><div class="card-body"><form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="replace_credential"><div class="mb-3"><label class="form-label" for="credential-username">Username</label><input class="form-control" id="credential-username" name="username" maxlength="320" autocomplete="off" required value=""></div><div class="mb-3"><label class="form-label" for="credential-password">New password</label><input class="form-control" type="password" id="credential-password" name="password" maxlength="4096" autocomplete="new-password" required value=""></div><button class="btn-accent" type="submit">Replace credential</button></form><form method="post" class="mt-3" onsubmit="return confirm('Revoke the stored website credential? The website registration will remain active.');"><?= csrf_field() ?><input type="hidden" name="action" value="revoke_credential"><button class="btn-secondary" type="submit">Revoke credential</button></form></div></section></div>
    <div class="col-xl-6"><section class="card h-100"><div class="card-header"><strong>Site-specific permissions</strong></div><div class="card-body"><p class="text-muted">No remote action is available in Phase 1. Missing or revoked grants are denied.</p><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="replace_scopes"><div class="row g-2"><?php foreach (WebsiteScopePolicy::PERMISSIONS as $code => $label): ?><div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" id="scope-<?= e($code) ?>" value="<?= e($code) ?>" <?= in_array($code, $activeScopes, true) ? 'checked' : '' ?> <?= $website['status'] !== 'active' ? 'disabled' : '' ?>><label class="form-check-label" for="scope-<?= e($code) ?>"><?= e($label) ?></label></div></div><?php endforeach; ?></div><button class="btn-accent mt-3" type="submit" <?= $website['status'] !== 'active' ? 'disabled' : '' ?>>Save permissions</button></form></div></section></div>
    <div class="col-xl-6"><section class="card h-100"><div class="card-header"><strong>Revoke website authorization</strong></div><div class="card-body"><p>Revocation disables the registration, all active permission grants, and all active stored credentials.</p><p class="text-muted">This does not rotate the password on the registered website. Rotate it there separately if needed.</p><form method="post" onsubmit="return confirm('Revoke this website and its active credentials?');"><?= csrf_field() ?><input type="hidden" name="action" value="revoke_website"><button class="btn-danger" type="submit" <?= $website['status'] !== 'active' ? 'disabled' : '' ?>>Revoke website</button></form></div></section></div>
    <div class="col-12"><section class="card"><div class="card-header"><strong>Security audit history</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Time</th><th>Event</th><th>Outcome</th><th>Details</th><th>Admin ID</th></tr></thead><tbody><?php if ($detail['audit'] === []): ?><tr><td colspan="5" class="text-center p-4">No security events recorded.</td></tr><?php endif; ?><?php foreach ($detail['audit'] as $event): $safeDetails = json_decode((string) ($event['details_json'] ?? ''), true); ?><tr><td><?= e((string) $event['created_at']) ?></td><td><?= e((string) $event['event_code']) ?></td><td><?= e((string) $event['outcome']) ?></td><td><?= e(is_array($safeDetails) ? implode(', ', array_map('strval', $safeDetails['permission_codes'] ?? [])) : '') ?></td><td><?= e((string) ($event['actor_user_id'] ?? 'system')) ?></td></tr><?php endforeach; ?></tbody></table></div></section></div>
  </div>
</main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
