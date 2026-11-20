<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/devpilot-bootstrap.php';
if (devpilot_initialize_runtime() !== null) {
    devpilot_unavailable();
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid project ID');
}

$registry = \DevPilot\Registry::getInstance();
$project = $registry->get($id);
if ($project === null) {
    http_response_code(404);
    exit('Project not found');
}

$discovery = \DevPilot\Discovery::detect($project->path);
$operations = new \DevPilot\Api\OperationQueue();
$projectOps = $operations->list(null, null, $id);

$adminPageTitle = 'Project: ' . $project->name;
$adminActiveNav = 'devpilot';
$unreadCount = 0;
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="mb-0">Project: <?= e($project->name) ?></h1>
    <a href="devpilot.php" class="btn-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to DevPilot</a>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <strong>Project Info</strong>
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-4">ID:</dt>
            <dd class="col-8"><?= $project->id ?></dd>

            <dt class="col-4">Language:</dt>
            <dd class="col-8"><?= e($project->language) ?></dd>

            <dt class="col-4">Framework:</dt>
            <dd class="col-8"><?= e($project->framework ?: 'None') ?></dd>

            <dt class="col-4">Database:</dt>
            <dd class="col-8"><?= e($project->database ?: 'None') ?></dd>

            <dt class="col-4">Environment:</dt>
            <dd class="col-8"><span class="badge-status badge-status-draft"><?= e($project->environment) ?></span></dd>

            <dt class="col-4">Status:</dt>
            <dd class="col-8"><span class="badge <?= $project->status === 'active' ? 'badge-status-published' : 'badge-status-draft' ?>"><?= e($project->status) ?></span></dd>

            <dt class="col-4">Path:</dt>
            <dd class="col-8" style="word-break: break-all;"><code><?= e($project->path) ?></code></dd>

            <dt class="col-4">Added:</dt>
            <dd class="col-8"><?= e(date('M j, Y', strtotime($project->createdAt))) ?></dd>

            <dt class="col-4">Updated:</dt>
            <dd class="col-8"><?= e(date('M j, Y', strtotime($project->updatedAt))) ?></dd>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <strong>Detection Results</strong>
        </div>
        <div class="card-body">
          <?php if (!empty($discovery['primaryLanguage'])): ?>
            <dl class="row mb-0">
              <dt class="col-4">Primary Language:</dt>
              <dd class="col-8"><?= e($discovery['primaryLanguage']) ?></dd>

              <?php if (!empty($discovery['frameworks'])): ?>
                <dt class="col-4">Frameworks:</dt>
                <dd class="col-8"><?= e(implode(', ', $discovery['frameworks'])) ?></dd>
              <?php endif; ?>

              <?php if (!empty($discovery['databases'])): ?>
                <dt class="col-4">Databases:</dt>
                <dd class="col-8"><?= e(implode(', ', $discovery['databases'])) ?></dd>
              <?php endif; ?>

              <?php if (!empty($discovery['technologies'])): ?>
                <dt class="col-4">Technologies:</dt>
                <dd class="col-8"><?= e(implode(', ', $discovery['technologies'])) ?></dd>
              <?php endif; ?>

              <dt class="col-4">Has Git:</dt>
              <dd class="col-8"><?= $discovery['hasGit'] ? '<span class="badge-status badge-status-published">Yes</span>' : '<span class="badge-status badge-status-draft">No</span>' ?></dd>

              <dt class="col-4">Has Docker:</dt>
              <dd class="col-8"><?= $discovery['hasDocker'] ? '<span class="badge-status badge-status-published">Yes</span>' : '<span class="badge-status badge-status-draft">No</span>' ?></dd>
            </dl>
          <?php else: ?>
            <p class="text-muted">Unable to detect technology stack.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <strong>Actions</strong>
        </div>
        <div class="card-body">
          <form action="devpilot-operations.php" method="post" style="display: none;" id="actionForm">
            <input type="hidden" name="projectId" value="<?= $project->id ?>">
            <input type="hidden" name="operation" id="operationInput">
            <?= csrf_field() ?>
          </form>

          <button type="button" onclick="queueAction('check_health')" class="btn-outline-accent w-100 mb-2">
            <i class="bi bi-heart-pulse" aria-hidden="true"></i> Check Health
          </button>

          <button type="button" onclick="queueAction('run_discovery')" class="btn-outline-accent w-100 mb-2">
            <i class="bi bi-search" aria-hidden="true"></i> Run Discovery
          </button>

          <button type="button" onclick="queueAction('get_status')" class="btn-outline-accent w-100">
            <i class="bi bi-info-circle" aria-hidden="true"></i> Get Status
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <strong>Operation History</strong>
          <span class="text-muted" style="font-size: 0.875rem;"><?= count($projectOps) ?> operations</span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($projectOps)): ?>
            <div class="p-4 text-center">
              <p class="mb-0" style="color: var(--admin-text-muted);">No operations recorded.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table mb-0">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Operation</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Completed</th>
                    <th style="text-align: right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($projectOps as $op): ?>
                    <tr>
                      <td>#<strong><?= $op['id'] ?></strong></td>
                      <td><?= e($op['operation']) ?></td>
                      <td>
                        <?php
                        $badgeClass = match($op['status']) {
                            'completed' => 'badge-status-published',
                            'processing' => 'badge',
                            'pending' => 'badge-status-draft',
                            default => 'badge-status-draft',
                        };
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= ucfirst($op['status']) ?></span>
                      </td>
                      <td><?= $op['startedAt'] ? e(date('M j, H:i', strtotime($op['startedAt']))) : '-' ?></td>
                      <td><?= $op['completedAt'] ? e(date('M j, H:i', strtotime($op['completedAt']))) : '-' ?></td>
                      <td style="text-align: right;">
                        <span class="badge-status badge-status-draft" style="font-size: 0.875rem;">Available</span>
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
  </div>
</main>
<script>
function queueAction(operation) {
  document.getElementById('operationInput').value = operation;
  document.getElementById('actionForm').submit();
}
</script>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
