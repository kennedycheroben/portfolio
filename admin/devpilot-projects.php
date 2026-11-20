<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/devpilot-bootstrap.php';
if (devpilot_initialize_runtime() !== null) {
    devpilot_unavailable();
}

$error = '';
$name = '';
$path = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = input_string($_POST, 'name');
    $path = input_string($_POST, 'path');
    $resolvedPath = realpath($path);

    if ($name === '' || text_length($name) > 120) {
        $error = 'Enter a project name up to 120 characters.';
    } elseif ($resolvedPath === false || !is_dir($resolvedPath) || !is_readable($resolvedPath)) {
        $error = 'Enter a readable directory that exists on this server.';
    } else {
        try {
            $discovery = \DevPilot\Discovery::detect($resolvedPath);
            $project = new \DevPilot\Project(
                id: 0,
                name: $name,
                path: $resolvedPath,
                language: (string) ($discovery['primaryLanguage'] ?? '')
            );
            $projectId = \DevPilot\Registry::getInstance()->add($project);
            header('Location: devpilot-project.php?id=' . $projectId . '&success=project_registered', true, 303);
            exit;
        } catch (\Throwable $exception) {
            error_log('DevPilot project registration failed: ' . get_class($exception));
            $error = 'The project could not be registered. Check DevPilot storage permissions.';
        }
    }
}

$adminPageTitle = 'Register DevPilot Project';
$adminActiveNav = 'devpilot';
$unreadCount = 0;
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="mb-0">Register Project</h1>
    <a href="devpilot.php" class="btn-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to DevPilot</a>
  </div>
  <div class="card"><div class="card-body">
    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <p>Register a project directory available to the DevPilot local agent.</p>
    <form method="post" action="devpilot-projects.php">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="name">Project name</label><input class="form-control" id="name" name="name" maxlength="120" required value="<?= e($name) ?>"></div>
      <div class="mb-3"><label class="form-label" for="path">Server directory path</label><input class="form-control" id="path" name="path" required value="<?= e($path) ?>" placeholder="/home/account/projects/example"></div>
      <button class="btn-accent" type="submit">Register Project</button>
    </form>
  </div></div>
</main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
