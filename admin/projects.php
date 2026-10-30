<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';

$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 25;
$totalProjects = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$pageCount = max(1, (int) ceil($totalProjects / $perPage));
$page = min($page, $pageCount);
$statement = $pdo->prepare('SELECT * FROM projects ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
$statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
$statement->execute();
$projects = $statement->fetchAll();

$adminPageTitle = 'Manage Projects';
$adminActiveNav = 'projects';
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="mb-0">Projects</h1>
    <a href="projects-add.php" class="btn-accent"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add Project</a>
  </div>

  <?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success" role="status"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i> Project added successfully.</div>
  <?php elseif (isset($_GET['updated'])): ?>
    <div class="alert alert-success" role="status"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i> Project updated successfully.</div>
  <?php elseif (isset($_GET['deleted'])): ?>
    <div class="alert alert-success" role="status"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i> Project deleted successfully.</div>
  <?php endif; ?>

  <div class="card">
    <?php if (empty($projects)): ?>
      <div class="card-body text-center py-5">
        <p class="mb-3" style="color: var(--admin-text-muted);">No projects recorded yet.</p>
        <a href="projects-add.php" class="btn-accent"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add your first project</a>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th style="width: 70px;">Image</th>
              <th>Title</th>
              <th>Category</th>
              <th>Status</th>
              <th>Featured</th>
              <th>Date</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($projects as $p): ?>
              <tr>
                <td>
                  <?php if (!empty($p['image_url'])): ?>
                    <img src="<?= e(project_image_url($p['image_url'])) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='<?= e(site_url('assets/img/cheroben_logo.webp')) ?>';">
                  <?php else: ?>
                    <span style="color: var(--admin-text-dim);">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <strong><?= e($p['title']) ?></strong>
                  <?php if (!empty($p['client'])): ?>
                    <div style="font-size: 0.8125rem; color: var(--admin-text-muted);"><?= e($p['client']) ?></div>
                  <?php endif; ?>
                </td>
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
                  <div class="d-inline-flex gap-2 align-items-center">
                    <a href="projects-edit.php?id=<?= (int) $p['id'] ?>" class="btn-admin-edit" aria-label="Edit <?= e($p['title']) ?>">
                      <i class="bi bi-pencil-square" aria-hidden="true"></i> Edit
                    </a>
                    <a href="../portfolio-details.php?id=<?= (int) $p['id'] ?>" target="_blank" rel="noopener noreferrer" class="btn-admin-view" title="View Case Study" aria-label="View case study for <?= e($p['title']) ?>">
                      <i class="bi bi-eye" aria-hidden="true"></i>
                    </a>
                    <form action="projects-delete.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this project?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                      <button type="submit" class="btn-admin-delete" title="Delete" aria-label="Delete <?= e($p['title']) ?>">
                        <i class="bi bi-trash3" aria-hidden="true"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pageCount > 1): ?>
        <nav aria-label="Project pages" class="pagination py-4">
          <?php for ($number = 1; $number <= $pageCount; $number++): ?>
            <a class="btn btn-sm <?= $number === $page ? 'btn-accent' : 'btn-admin-view' ?>" href="?page=<?= $number ?>"><?= $number ?></a>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>