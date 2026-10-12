<?php
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Projects - Portfolio</title>
  <link rel="icon" type="image/x-icon" href="../favicon.ico">
  <link rel="icon" type="image/webp" href="../assets/img/cheroben_logo.webp">
  <link rel="apple-touch-icon" href="../assets/img/cheroben_logo.webp">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../assets/css/main.css?v=20261011_1" rel="stylesheet">
  <style>
    .admin-body {
      background: var(--bg);
      min-height: 100vh;
      color: var(--text);
    }

    .admin-sidebar {
      background: var(--surface);
      border-right: 1px solid var(--line);
      width: 260px;
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      height: 100svh;
      padding: 28px 20px;
      overflow-y: auto;
      z-index: 1000;
      display: flex;
      flex-direction: column;
    }

    .admin-sidebar .brand {
      font-size: var(--font-size-step-2);
      font-weight: 700;
      color: var(--accent);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 36px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--line);
    }

    .admin-sidebar .brand i {
      font-size: 1.4em;
    }

    .admin-sidebar .nav {
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;
    }

    .admin-sidebar .nav-link {
      color: var(--text-dim);
      padding: 12px 16px;
      border-radius: var(--radius-sm);
      font-size: var(--font-size-step-1);
      display: flex;
      align-items: center;
      gap: 12px;
      transition: background var(--transition-fast), color var(--transition-fast);
      text-decoration: none;
      border: 1px solid transparent;
    }

    .admin-sidebar .nav-link:hover,
    .admin-sidebar .nav-link.active {
      background: var(--glass);
      border-color: var(--glass-border);
      color: var(--accent);
    }

    .admin-sidebar .nav-link i {
      font-size: 1.2em;
      width: 22px;
      text-align: center;
    }

    .admin-sidebar .sign-out {
      margin-top: auto;
      padding-top: 20px;
      border-top: 1px solid var(--line);
    }

    .admin-main {
      margin-left: 260px;
      padding: 40px;
      min-height: 100vh;
    }

    .admin-main h1 {
      font-size: var(--font-size-step-4);
      font-weight: 600;
      margin-bottom: 32px;
      color: var(--text);
    }

    .alert {
      border-radius: var(--radius-sm);
      padding: 14px 20px;
      font-size: var(--font-size-step-1);
      margin-bottom: 24px;
    }

    .alert-success {
      background: rgba(34, 197, 94, .12);
      border: 1px solid rgba(34, 197, 94, .35);
      color: #86efac;
    }

    .table {
      color: var(--text);
      font-size: var(--font-size-step-1);
    }

    .table th {
      border-color: var(--line);
      font-weight: 600;
      color: var(--text-dim);
      font-size: var(--font-size-step-0);
      text-transform: uppercase;
      letter-spacing: .08em;
      padding: 16px 20px;
    }

    .table td {
      border-color: var(--line-soft);
      vertical-align: middle;
      padding: 16px 20px;
    }

    .table tbody tr {
      transition: background var(--transition-fast);
    }

    .table tbody tr:hover {
      background: rgba(255, 255, 255, .02);
    }

    .table img {
      width: 56px;
      height: 42px;
      object-fit: cover;
      border-radius: 6px;
    }

    .badge-featured {
      background: var(--accent);
      color: var(--contrast-color);
      font-size: var(--font-size-step-0);
      padding: 4px 10px;
      border-radius: 999px;
      font-weight: 600;
    }

    .btn-sm {
      padding: 8px 16px;
      font-size: var(--font-size-step-1);
      border-radius: var(--radius-sm);
    }

    .btn-outline-light {
      background: transparent;
      border-color: var(--line);
      color: var(--text);
    }

    .btn-outline-light:hover {
      background: var(--glass);
      border-color: var(--glass-border);
      color: var(--accent);
    }

    .btn-outline-danger {
      background: transparent;
      border-color: rgba(239, 68, 68, .4);
      color: #fca5a5;
    }

    .btn-outline-danger:hover {
      background: rgba(239, 68, 68, .12);
      border-color: #ef4444;
      color: #fecaca;
    }

    .btn-accent {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: var(--radius-sm);
      font-weight: 600;
      font-size: var(--font-size-step-1);
    }

    .btn-accent:hover {
      background: var(--accent-glow);
      border-color: var(--accent-glow);
      color: #fff;
    }

    .card {
      background: var(--glass);
      border: 1px solid var(--glass-border);
      border-radius: var(--radius);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
    }

    .card-header {
      background: transparent;
      border-bottom: 1px solid var(--line);
      padding: 20px 24px;
    }

    .card-body {
      padding: 0;
    }

    .pagination {
      display: flex;
      gap: 8px;
      justify-content: center;
      margin-top: 32px;
    }

    .pagination .btn {
      min-width: 40px;
      padding: 8px 14px;
    }

    /* Page entry animation */
    @keyframes fadeSlideUp {
      from {
        opacity: 0;
        transform: translateY(24px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .admin-main > * {
      animation: fadeSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
      opacity: 0;
    }

    .admin-main > *:nth-child(1) { animation-delay: 0.05s; }
    .admin-main > *:nth-child(2) { animation-delay: 0.1s; }
    .admin-main > *:nth-child(3) { animation-delay: 0.15s; }
    .admin-main > *:nth-child(4) { animation-delay: 0.2s; }
    .admin-main > *:nth-child(5) { animation-delay: 0.25s; }
    .admin-main > *:nth-child(6) { animation-delay: 0.3s; }

    /* Sidebar nav link active glow */
    .admin-sidebar .nav-link.active {
      position: relative;
      overflow: hidden;
    }

    .admin-sidebar .nav-link.active::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--accent), transparent 70%), transparent);
      animation: shimmer 2.5s ease-in-out infinite;
    }

    @keyframes shimmer {
      0% { transform: translateX(-100%); }
      100% { transform: translateX(100%); }
    }

    /* Mobile responsive */
    .sidebar-toggle {
      display: none;
      position: fixed;
      top: 16px;
      left: 16px;
      z-index: 1060;
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius-sm);
      padding: 10px 12px;
      cursor: pointer;
      font-size: 1.4rem;
      color: var(--text);
      line-height: 1;
    }

    .sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1040;
      background: rgba(0, 0, 0, 0.5);
    }

    .sidebar-overlay.show {
      display: block;
    }

    @media (max-width: 980px) {
      .sidebar-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .admin-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 1050;
        width: 280px;
        max-width: 85vw;
      }

      .admin-sidebar.open {
        transform: translateX(0);
      }

      .admin-main {
        margin-left: 0;
        padding: 80px 20px 32px;
      }

      .stat-card .number {
        font-size: var(--font-size-step-4);
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .admin-main > * {
        animation: none;
        opacity: 1;
      }
      .admin-sidebar .nav-link.active::before {
        animation: none;
      }
    }
  </style>
  <script>
    function toggleSidebar() {
      document.querySelector('.admin-sidebar').classList.toggle('open');
      document.querySelector('.sidebar-overlay').classList.toggle('show');
    }
  </script>
</head>

<body class="admin-body">
  <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle navigation"><i class="bi bi-list"></i></button>
  <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
  <div class="admin-sidebar">
    <a href="index.php" class="brand"><i class="bi bi-code-slash"></i> Portfolio</a>
    <nav class="nav" aria-label="Admin navigation">
      <a href="index.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="projects.php" class="nav-link active"><i class="bi bi-folder"></i> Projects</a>
      <a href="messages.php" class="nav-link"><i class="bi bi-envelope"></i> Messages</a>
      <a href="../index.php" class="nav-link"><i class="bi bi-box-arrow-up-right"></i> View Site</a>
      <div class="sign-out">
        <form action="../logout.php" method="post"><?= csrf_field() ?><button type="submit" class="nav-link border-0 bg-transparent w-100 text-start"><i class="bi bi-box-arrow-right"></i> Sign Out</button></form>
      </div>
    </nav>
  </div>

  <div class="admin-main">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="mb-0">Projects</h1>
      <a href="projects-add.php" class="btn-accent"><i class="bi bi-plus-lg"></i> Add Project</a>
    </div>

    <?php if (isset($_GET['added'])): ?>
      <div class="alert alert-success">Project added successfully.</div>
    <?php elseif (isset($_GET['updated'])): ?>
      <div class="alert alert-success">Project updated successfully.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
      <div class="alert alert-success">Project deleted successfully.</div>
    <?php endif; ?>

    <div class="card">
      <?php if (empty($projects)): ?>
        <div class="card-body text-center py-5">
          <p class="mb-3" style="color: var(--text-dim);">No projects yet.</p>
          <a href="projects-add.php" class="btn-accent"><i class="bi bi-plus-lg"></i> Add your first project</a>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-borderless mb-0">
            <thead>
              <tr>
                <th>Image</th>
                <th>Title</th>
                <th>Category</th>
                <th>Client</th>
                <th>Featured</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($projects as $p): ?>
                <tr>
                  <td>
                    <?php if ($p['image_url']): ?>
                      <img src="<?= e(project_image_url($p['image_url'])) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='<?= e(site_url('assets/img/cheroben_logo.webp')) ?>';">
                    <?php else: ?>
                      <span style="color: var(--text-dim);">—</span>
                    <?php endif; ?>
                  </td>
                  <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                  <td><?= htmlspecialchars($p['category']) ?></td>
                  <td><?= htmlspecialchars($p['client'] ?: '—') ?></td>
                  <td><?= $p['featured'] ? '<span class="badge-featured">Featured</span>' : '<span style="color:var(--text-dim);">—</span>' ?></td>
                  <td><?= $p['date'] ? date('M j, Y', strtotime($p['date'])) : '<span style="color:var(--text-dim);">—</span>' ?></td>
                  <td>
                    <a href="projects-edit.php?id=<?= $p['id'] ?>" class="btn btn-outline-light btn-sm" aria-label="Edit <?= htmlspecialchars($p['title']) ?>"><i class="bi bi-pencil"></i></a>
                    <form action="projects-delete.php" method="post" class="d-inline" onsubmit="return confirm('Delete this project?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button type="submit" class="btn btn-outline-danger btn-sm" aria-label="Delete <?= htmlspecialchars($p['title']) ?>"><i class="bi bi-trash"></i></button></form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pageCount > 1): ?>
          <nav aria-label="Project pages" class="pagination">
            <?php for ($number = 1; $number <= $pageCount; $number++): ?>
              <a class="btn btn-sm <?= $number === $page ? 'btn-accent' : 'btn-outline-light' ?>" href="?page=<?= $number ?>"><?= $number ?></a>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>