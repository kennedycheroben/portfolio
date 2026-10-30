<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';

$projectCount = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$publishedCount = (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'published'")->fetchColumn();
$featuredCount = (int) $pdo->query('SELECT COUNT(*) FROM projects WHERE featured = 1')->fetchColumn();
$unreadCount = (int) $pdo->query('SELECT COUNT(*) FROM contacts WHERE is_read = 0')->fetchColumn();
$recentProjects = $pdo->query('SELECT * FROM projects ORDER BY created_at DESC LIMIT 5')->fetchAll();

$adminPageTitle = 'Dashboard';
$adminActiveNav = 'dashboard';
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="mb-0">Dashboard</h1>
    <a href="projects-add.php" class="btn-accent"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add Project</a>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="number"><?= $projectCount ?></div>
        <div class="label">Total Projects</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="number"><?= $publishedCount ?></div>
        <div class="label">Published Projects</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="number"><?= $featuredCount ?></div>
        <div class="label">Featured Work</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="number"><?= $unreadCount ?></div>
        <div class="label">Unread Messages</div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <strong>Recent Projects</strong>
      <a href="projects.php" class="text-decoration-none" style="color: var(--admin-accent); font-weight: 500; font-size: 0.875rem;">View all projects <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="card-body p-0">
      <?php if (empty($recentProjects)): ?>
        <div class="p-5 text-center">
          <p class="mb-3" style="color: var(--admin-text-muted);">No projects recorded yet.</p>
          <a href="projects-add.php" class="btn-accent"><i class="bi bi-plus-lg"></i> Add your first project</a>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Title</th>
                <th>Category</th>
                <th>Status</th>
                <th>Featured</th>
                <th>Date</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentProjects as $p): ?>
                <tr>
                  <td><strong><?= e($p['title']) ?></strong></td>
                  <td><?= e(ucwords(str_replace('-', ' ', (string) $p['category']))) ?></td>
                  <td>
                    <?php if (($p['status'] ?? 'published') === 'published'): ?>
                      <span class="badge-status badge-status-published"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Published</span>
                    <?php else: ?>
                      <span class="badge-status badge-status-draft"><i class="bi bi-dash-circle-fill" aria-hidden="true"></i> Draft</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= !empty($p['featured']) ? '<span class="badge-status badge-status-featured"><i class="bi bi-star-fill" aria-hidden="true"></i> Featured</span>' : '<span style="color:var(--admin-text-dim);">—</span>' ?>
                  </td>
                  <td><?= !empty($p['date']) ? date('M j, Y', strtotime((string) $p['date'])) : '<span style="color:var(--admin-text-dim);">—</span>' ?></td>
                  <td style="text-align: right;">
                    <div class="d-inline-flex gap-2">
                      <a href="projects-edit.php?id=<?= (int) $p['id'] ?>" class="btn-admin-edit" aria-label="Edit <?= e($p['title']) ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i> Edit</a>
                      <a href="../portfolio-details.php?id=<?= (int) $p['id'] ?>" target="_blank" rel="noopener noreferrer" class="btn-admin-view" title="View Case Study" aria-label="View case study for <?= e($p['title']) ?>"><i class="bi bi-eye" aria-hidden="true"></i></a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>