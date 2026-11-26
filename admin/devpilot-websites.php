<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
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
$created = false;
$connectorProjectId = 'php-' . substr(hash('sha256', rtrim((string) (getenv('APP_URL') ?: ''), '/') . '|' . dirname(__DIR__), false), 0, 24);
$connectorStatus = null;
try { $connectorStatus = WebsiteServices::connectedProjects($pdo)->status($adminId, $connectorProjectId); } catch (Throwable) { $connectorStatus = null; }
$appEnvironment = (string) getenv('APP_ENV');
$connectorEnabled = in_array($appEnvironment, ['development','local','staging'], true)
    || ($appEnvironment === 'production' && getenv('DEVPILOT_CONNECTOR_SECURITY_VERIFIED') === '1');
$postedUsername = '';
$postedPassword = '';
$wipePostedCredentials = static function () use (&$postedUsername, &$postedPassword): void {
    foreach (['postedUsername', 'postedPassword'] as $variable) {
        if ($$variable !== '' && function_exists('sodium_memzero')) {
            sodium_memzero($$variable);
        } else {
            $$variable = '';
        }
    }
};
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedUsername = input_string($_POST, 'username');
    $postedPassword = input_string($_POST, 'password', false);
    unset($_POST['username'], $_POST['password']);
    $csrf = $_POST['csrf_token'] ?? '';
    if (!is_string($csrf) || $csrf === '' || !hash_equals(csrf_token(), $csrf)) {
        $wipePostedCredentials();
        http_response_code(419);
        exit('Your session expired. Refresh the page and try again.');
    }
    try {
        $permissions = $_POST['permissions'] ?? [];
        if (!is_array($permissions)) {
            throw new InvalidArgumentException('Permissions must be a list.');
        }
        $id = $service->register([
            'name' => input_string($_POST, 'name'),
            'admin_url' => input_string($_POST, 'admin_url'),
            'environment' => input_string($_POST, 'environment'),
            'mfa_expected' => isset($_POST['mfa_expected']),
            'permissions' => $permissions,
            'credentials' => [
                'username' => $postedUsername,
                'password' => $postedPassword,
            ],
        ], $adminId);
        $_POST = [];
        $wipePostedCredentials();
        header('Location: devpilot-website.php?id=' . $id . '&created=1', true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('DevPilot website registration failed: ' . get_class($exception));
        $error = 'The website could not be registered. Check the database and vault key configuration.';
    } finally {
        $wipePostedCredentials();
    }
}

try {
    $websites = $service->list($adminId);
} catch (Throwable $exception) {
    error_log('DevPilot website list failed: ' . get_class($exception));
    http_response_code(503);
    exit('Website management is temporarily unavailable.');
}

$adminPageTitle = 'Registered Websites';
$adminActiveNav = 'devpilot-websites';
$unreadCount = 0;
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div><h1 class="mb-1">Registered Websites</h1><p class="text-muted mb-0">Legacy website authorizations are preserved. Project connectors use one-time pairing and scoped bearer tokens.</p></div>
    <a class="btn-secondary" href="devpilot.php">Back to DevPilot</a>
  </div>

  <section class="card mb-4" aria-labelledby="connect-devpilot-heading">
    <div class="card-header"><strong id="connect-devpilot-heading">Connect this project to DevPilot</strong></div>
    <div class="card-body">
      <p class="mb-3">DevPilot identifies this project from its server-side origin and identity. No project URL or credential is entered here.</p>
      <?php if (!$connectorEnabled): ?><div class="alert alert-info" role="status">Project connections are disabled in production until the required security review is complete.</div><?php endif; ?>
      <p id="connector-feedback" class="visually-hidden" role="status" aria-live="polite"></p>
      <form class="connector-action" method="post" action="api/devpilot-connector.php">
        <?= csrf_field() ?><input type="hidden" name="action" value="connect"><button class="btn-accent" type="submit" <?= $connectorEnabled ? '' : 'disabled' ?>><?= ($connectorStatus['status'] ?? '') === 'active' ? 'Reconnect DevPilot' : 'Connect DevPilot' ?></button>
      </form>
      <?php if (is_array($connectorStatus)): ?>
        <hr><dl class="row mb-3">
          <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= e((string) $connectorStatus['status']) ?></dd>
          <dt class="col-sm-3">Connected DevPilot</dt><dd class="col-sm-9">DevPilot · <?= e((string) $connectorStatus['project_name']) ?></dd>
          <dt class="col-sm-3">Project identity</dt><dd class="col-sm-9"><?= e((string) $connectorStatus['project_id']) ?> · <?= e((string) $connectorStatus['origin']) ?></dd>
          <dt class="col-sm-3">Granted permissions</dt><dd class="col-sm-9"><?= e(implode(', ', (array) $connectorStatus['scopes'])) ?></dd>
          <dt class="col-sm-3">Last successful connection</dt><dd class="col-sm-9"><?= is_numeric($connectorStatus['connected_at'] ?? null) ? e(date('Y-m-d H:i:s T', (int) $connectorStatus['connected_at'])) : '—' ?></dd>
          <dt class="col-sm-3">Last seen</dt><dd class="col-sm-9"><?= is_numeric($connectorStatus['last_seen_at'] ?? null) ? e(date('Y-m-d H:i:s T', (int) $connectorStatus['last_seen_at'])) : '—' ?></dd>
          <dt class="col-sm-3">Last inspection</dt><dd class="col-sm-9"><?= is_numeric($connectorStatus['last_inspected_at'] ?? null) ? e(date('Y-m-d H:i:s T', (int) $connectorStatus['last_inspected_at'])) : '—' ?></dd>
          <?php if (is_array($connectorStatus['inspection'] ?? null)): $inspection = $connectorStatus['inspection']; ?>
            <dt class="col-sm-3">Inspection health</dt><dd class="col-sm-9"><?= e((string) ($inspection['connection_health'] ?? 'unknown')) ?> · <?= e((string) ($inspection['technology'] ?? 'unknown')) ?> · <?= e((string) ($inspection['framework'] ?? 'unknown')) ?> · PHP <?= e((string) ($inspection['runtime'] ?? '')) ?></dd>
          <?php elseif (!empty($connectorStatus['last_error'])): ?><dt class="col-sm-3">Last inspection error</dt><dd class="col-sm-9"><?= e((string) $connectorStatus['last_error']) ?></dd><?php endif; ?>
        </dl>
        <?php if ($connectorStatus['status'] === 'active'): ?>
          <div class="d-flex gap-2 flex-wrap">
            <form class="connector-action" method="post" action="api/devpilot-connector.php"><?= csrf_field() ?><input type="hidden" name="action" value="refresh"><button class="btn-secondary" type="submit" <?= $connectorEnabled ? '' : 'disabled' ?>>Refresh inspection</button></form>
            <form class="connector-action" method="post" action="api/devpilot-connector.php"><?= csrf_field() ?><input type="hidden" name="action" value="disconnect"><button class="btn-secondary" type="submit">Disconnect DevPilot</button></form>
          </div>
        <?php endif; ?>
      <?php endif; ?>
      <script>
        document.querySelectorAll('.connector-action').forEach(form => form.addEventListener('submit', async event => {
          event.preventDefault(); const button = form.querySelector('button'); const feedback = document.getElementById('connector-feedback');
          button.disabled = true; feedback.classList.remove('visually-hidden'); feedback.textContent = 'Working…';
          try { const response = await fetch(form.action, {method:'POST', headers:{'X-CSRF-Token':form.querySelector('[name="csrf_token"]').value}, body:new FormData(form)}); const data = await response.json(); if (!response.ok || !data.success) throw new Error(data.error || 'Connector action failed.'); feedback.textContent = data.message || 'Connector updated.'; location.reload(); }
          catch (error) { feedback.textContent = error.message; alert(error.message); button.disabled = false; }
        }));
      </script>
    </div>
  </section>

  <section class="card mb-4" aria-labelledby="register-website-heading">
    <div class="card-header"><strong id="register-website-heading">Register a website</strong></div>
    <div class="card-body">
      <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
      <div class="alert alert-info" role="note">Enter credentials only for a website you are authorized to manage. Passwords are encrypted before database storage and are never shown again. MFA, if required, will be completed manually in a later phase.</div>
      <form method="post" action="devpilot-websites.php" autocomplete="off">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="website-name">Website name</label><input class="form-control" id="website-name" name="name" maxlength="120" required value="<?= e(input_string($_POST, 'name')) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="website-environment">Environment</label>
            <?php $environment = input_string($_POST, 'environment') ?: 'demo'; ?>
            <select class="form-select" id="website-environment" name="environment" required>
              <?php foreach (['demo' => 'Demo', 'staging' => 'Staging'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $environment === $value ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12"><label class="form-label" for="website-admin-url">HTTPS administrator URL</label><input class="form-control" type="url" id="website-admin-url" name="admin_url" maxlength="2048" required value="<?= e(input_string($_POST, 'admin_url')) ?>" placeholder="https://example.org/admin"><div class="form-text">Phase 1 validates the URL format only. No DNS lookup or website request is made.</div></div>
          <div class="col-md-6"><label class="form-label" for="website-username">Authorized login username</label><input class="form-control" type="text" id="website-username" name="username" maxlength="320" autocomplete="off" required value=""></div>
          <div class="col-md-6"><label class="form-label" for="website-password">Authorized login password</label><input class="form-control" type="password" id="website-password" name="password" maxlength="4096" autocomplete="new-password" required value=""></div>
          <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="mfa-expected" name="mfa_expected" value="1" <?= isset($_POST['mfa_expected']) ? 'checked' : '' ?>><label class="form-check-label" for="mfa-expected">This website may require MFA; I will complete it manually if prompted later.</label></div></div>
          <div class="col-12"><fieldset><legend class="form-label">Authorized demo-management scope</legend><p class="form-text">No permission is selected by default. These grants are stored in Phase 1; no website actions are available yet.</p><div class="row g-2">
            <?php $selectedPermissions = is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : []; foreach (WebsiteScopePolicy::PERMISSIONS as $code => $label): ?>
              <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" id="scope-<?= e($code) ?>" name="permissions[]" value="<?= e($code) ?>" <?= in_array($code, $selectedPermissions, true) ? 'checked' : '' ?>><label class="form-check-label" for="scope-<?= e($code) ?>"><?= e($label) ?></label></div></div>
            <?php endforeach; ?>
          </div></fieldset></div>
          <div class="col-12"><button class="btn-accent" type="submit">Save website authorization</button></div>
        </div>
      </form>
    </div>
  </section>

  <section class="card" aria-labelledby="registered-sites-heading">
    <div class="card-header"><strong id="registered-sites-heading">Websites you manage</strong></div>
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>Administrator URL</th><th>Environment</th><th>Access</th><th>Registered</th><th></th></tr></thead><tbody>
    <?php if ($websites === []): ?><tr><td colspan="6" class="text-center p-4">No websites registered.</td></tr><?php endif; ?>
    <?php foreach ($websites as $website): ?>
      <tr><td><strong><?= e($website['name']) ?></strong></td><td><?= e($website['admin_url']) ?></td><td><?= e(ucfirst($website['environment'])) ?></td><td><?= e(ucfirst($website['status'])) ?> · <?= (int) $website['active_scope_count'] ?> active permissions · <?= e((string) ($website['credential_status'] ?? 'no credential')) ?> credential</td><td><?= e((string) $website['created_at']) ?></td><td><a href="devpilot-website.php?id=<?= (int) $website['id'] ?>" class="btn-admin-view">Manage</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </section>
</main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
