<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/devpilot-bootstrap.php';
if (devpilot_initialize_runtime() !== null) {
    devpilot_unavailable();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $projectId = filter_input(INPUT_POST, 'projectId', FILTER_VALIDATE_INT) ?: 0;
    $operation = is_string($_POST['operation'] ?? null) ? $_POST['operation'] : '';
    $allowedOperations = ['check_health', 'run_discovery', 'get_status'];
    $registry = \DevPilot\Registry::getInstance();
    if ($projectId <= 0 || $registry->get($projectId) === null || !in_array($operation, $allowedOperations, true)) {
        http_response_code(400);
        exit('Invalid operation request');
    }
    (new \DevPilot\Api\OperationQueue())->queue($projectId, $operation, [], (int) $_SESSION['user_id']);
    header('Location: devpilot-project.php?id=' . $projectId . '&success=operation_queued', true, 303);
    exit;
}

$registry = \DevPilot\Registry::getInstance();
$operations = new \DevPilot\Api\OperationQueue();
$ops = $operations->list();

$adminPageTitle = 'Operations';
$adminActiveNav = 'devpilot';
$unreadCount = 0;
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="mb-0">Operations</h1>
    <a href="devpilot.php" class="btn-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to DevPilot</a>
  </div>

  <?php if (empty($ops)): ?>
    <div class="card">
      <div class="card-body p-5 text-center">
        <p class="mb-3" style="color: var(--admin-text-muted);">No operations recorded.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Project</th>
            <th>Operation</th>
            <th>Status</th>
            <th>Device</th>
            <th>Admin ID</th>
            <th>Queued</th>
            <th>Started</th>
            <th>Completed</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ops as $op): ?>
            <tr>
              <td>#<strong><?= $op['id'] ?></strong></td>
              <td>
                <?php if ($op['projectId']): ?>
                  <?php
                  $proj = $registry->get($op['projectId']);
                  echo $proj ? e($proj->name) : 'Unknown';
                  ?>
                <?php else: ?>
                  N/A
                <?php endif; ?>
              </td>
              <td><?= e($op['operation']) ?></td>
              <td>
                <?php
                $badgeClass = match($op['status']) {
                  'completed' => 'badge-status-published',
                  'processing' => 'badge',
                  'pending' => 'badge-status-draft',
                  'failed' => 'badge',
                  'cancelled' => 'badge',
                };
                $statusText = match($op['status']) {
                  'completed' => 'Completed',
                  'processing' => 'Processing',
                  'pending' => 'Pending',
                  'failed' => 'Failed',
                  'cancelled' => 'Cancelled',
                };
                ?>
                <span class="badge <?= $badgeClass ?>"><?= $statusText ?></span>
              </td>
              <td><?= $op['deviceId'] ? e($op['deviceId']) : '<span class="text-muted">Not assigned</span>' ?></td>
              <td><?= $op['adminId'] ? $op['adminId'] : '-' ?></td>
              <td><?= $op['approvedAt'] ? e(date('M j, H:i', strtotime($op['approvedAt']))) : '-' ?></td>
              <td><?= $op['startedAt'] ? e(date('M j, H:i', strtotime($op['startedAt']))) : '-' ?></td>
              <td><?= $op['completedAt'] ? e(date('M j, H:i', strtotime($op['completedAt']))) : '-' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
