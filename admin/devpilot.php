<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/devpilot-bootstrap.php';
if (devpilot_initialize_runtime() !== null) {
    devpilot_unavailable();
}

$adminPageTitle = 'DevPilot';
$adminActiveNav = 'devpilot';
$unreadCount = 0;
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="mb-0">DevPilot Dashboard</h1>
    <a href="devpilot-projects.php" class="btn-accent"><i class="bi bi-plus-lg" aria-hidden="true"></i> Register Project</a>
  </div>

  <?php
    $registry = \DevPilot\Registry::getInstance();
    $devices = \DevPilot\Api\DeviceAuth::listRegisteredDevices();
    $operations = new \DevPilot\Api\OperationQueue();
    $ops = $operations->list();

    $deviceStatus = [];
    foreach ($devices as $deviceId => $deviceInfo) {
        $lastOp = null;
        foreach ($ops as $op) {
            if ($op['deviceId'] === $deviceId) {
                $lastOp = $op;
            }
        }

        $deviceStatus[$deviceId] = [
            'id' => $deviceId,
            'name' => $deviceInfo['name'] ?? 'Unknown',
            'registeredAt' => $deviceInfo['registeredAt'] ?? null,
            'lastSeen' => $deviceInfo['lastSeen'] ?? null,
            'revoked' => $deviceInfo['revoked'] ?? false,
            'online' => isset($deviceInfo['lastSeen']) && (time() - strtotime($deviceInfo['lastSeen'])) < 300,
            'lastOperation' => $lastOp,
        ];
    }

    $projects = $registry->list();
    $healthChecks = [];
    foreach ($projects as $project) {
        $discovery = \DevPilot\Discovery::detect($project->path);
        $healthChecks[$project->id] = $discovery;
    }
  ?>

  <div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="number"><?= count($projects) ?></div>
        <div class="label">Registered Projects</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="number"><?= count(array_filter($deviceStatus, fn($d) => !$d['revoked'] && $d['online'])) ?></div>
        <div class="label">Online Devices</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="number"><?= count($ops) ?></div>
        <div class="label">Pending Operations</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="number"><?= count(\DevPilot\Api\DeviceAuth::listRegisteredDevices()) ?></div>
        <div class="label">Registered Devices</div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <strong>Registered Projects</strong>
        </div>
        <div class="card-body p-0">
          <?php if (empty($projects)): ?>
            <div class="p-4 text-center">
              <p class="mb-3" style="color: var(--admin-text-muted);">No projects registered yet.</p>
              <a href="devpilot-projects.php" class="btn-accent"><i class="bi bi-plus-lg"></i> Register first project</a>
            </div>
          <?php else: ?>
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Language</th>
                  <th>Framework</th>
                  <th>Status</th>
                  <th style="text-align: right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($projects as $project): ?>
                  <tr>
                    <td><strong><?= e($project->name) ?></strong></td>
                    <td><?= e($project->language) ?></td>
                    <td><?= e($project->framework ?: '-') ?></td>
                    <td>
                      <?php if ($project->status === 'active'): ?>
                        <span class="badge-status badge-status-published"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Active</span>
                      <?php else: ?>
                        <span class="badge-status badge-status-draft"><i class="bi bi-dash-circle-fill" aria-hidden="true"></i> Draft</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                      <a href="devpilot-project.php?id=<?= $project->id ?>" class="btn-admin-view" title="View details"><i class="bi bi-eye" aria-hidden="true"></i></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <strong>Connected Devices</strong>
        </div>
        <div class="card-body">
          <?php if (empty($deviceStatus)): ?>
            <p class="text-muted">No devices registered.</p>
          <?php else: ?>
            <?php foreach ($deviceStatus as $device): ?>
              <div class="d-flex justify-content-between align-items-center mb-3" style="padding: 0.75rem; border: 1px solid var(--admin-border); border-radius: 0.5rem;">
                <div>
                  <strong><?= e($device['name']) ?></strong>
                  <br>
                  <small style="color: var(--admin-text-dim);">ID: <?= e($device['id']) ?></small>
                  <br>
                  <small style="color: <?= $device['online'] ? 'var(--success-color)' : 'var(--danger-color)' ?>;">
                    <?= $device['online'] ? 'Online' : 'Offline' ?>
                    <?php if ($device['lastSeen']): ?>
                      (Last seen: <?= e(date('M j, H:i', strtotime($device['lastSeen']))) ?>)
                    <?php endif; ?>
                  </small>
                </div>
                <form action="devpilot-device-delete.php" method="post" onsubmit="return confirm('Revoke this device?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="deviceId" value="<?= e($device['id']) ?>">
                  <?php if (!$device['revoked']): ?>
                    <button type="submit" class="btn-danger btn-sm" title="Revoke device"><i class="bi bi-trash" aria-hidden="true"></i> Revoke</button>
                  <?php endif; ?>
                </form>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <strong>Pending Operations</strong>
          <a href="devpilot-operations.php" class="text-decoration-none" style="color: var(--admin-accent); font-weight: 500; font-size: 0.875rem;">View all <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="card-body p-0">
          <?php
          $pendingOps = array_filter($ops, fn($o) => $o['status'] === 'pending');
          ?>
          <?php if (empty($pendingOps)): ?>
            <div class="p-4 text-center">
              <p class="mb-3" style="color: var(--admin-text-muted);">No pending operations.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table mb-0">
                <thead>
                  <tr>
                    <th>Project</th>
                    <th>Operation</th>
                    <th>Queued</th>
                    <th style="text-align: right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pendingOps as $op): ?>
                    <tr>
                      <?php
                      $proj = $registry->get($op['projectId']);
                      ?>
                      <td><strong><?= $proj ? e($proj->name) : e(\DevPilot\Api\OperationQueue::class) ?></strong></td>
                      <td><?= e($op['operation']) ?></td>
                      <td><?= e(date('M j, H:i', strtotime($op['approvedAt']))) ?></td>
                      <td style="text-align: right;">
                        <span class="badge-status badge-status-draft">Pending</span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <strong>System Status</strong>
        </div>
        <div class="card-body">
          <?php
          $registryPath = \DevPilot\Config::getRegistryPath();
          $devpilotRoot = \DevPilot\Config::getRoot();

          $checks = [
              'Registry' => is_readable($registryPath) || !file_exists($registryPath),
              'Logger Path' => is_writable($devpilotRoot . '/logs'),
              'Adapters Directory' => is_dir($devpilotRoot . '/adapters'),
              'API Directory' => is_dir($devpilotRoot . '/api'),
              'Devices File' => is_writable(dirname($registryPath)),
          ];
          ?>

          <?php foreach ($checks as $name => $status): ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span><?= $name ?>:</span>
              <span class="badge <?= $status ? 'badge-status-published' : 'badge-status-draft' ?>">
                <?= $status ? 'OK' : 'Issues' ?>
              </span>
            </div>
          <?php endforeach; ?>

          <hr style="margin: 1rem 0;">

          <details>
            <summary class="cursor-pointer" style="color: var(--admin-accent); font-size: 0.875rem;">View DevPilot info</summary>
            <div style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--admin-text-dim);">
              <p>DevPilot Root: <code><?= e($devpilotRoot) ?></code></p>
              <p>Registry: <code><?= e($registryPath) ?></code></p>
              <p>Version: 1.0.0</p>
            </div>
          </details>
        </div>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
