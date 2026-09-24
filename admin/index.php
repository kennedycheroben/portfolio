<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';

$projectCount = $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$userCount = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$featuredCount = $pdo->query('SELECT COUNT(*) FROM projects WHERE featured = 1')->fetchColumn();
$messageCount = $pdo->query('SELECT COUNT(*) FROM contacts WHERE is_read = 0')->fetchColumn();
$recentProjects = $pdo->query('SELECT * FROM projects ORDER BY created_at DESC LIMIT 5')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - Portfolio</title>
  <link rel="icon" type="image/x-icon" href="../favicon.ico">
  <link rel="icon" type="image/webp" href="../assets/img/cheroben_logo.webp">
  <link rel="apple-touch-icon" href="../assets/img/cheroben_logo.webp">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../assets/css/main.css?v=20260911_2" rel="stylesheet">
  <style>
    .admin-body {
      background: var(--background-color);
      min-height: 100vh;
    }

    .admin-sidebar {
      background: var(--surface-color);
      border-right: 1px solid color-mix(in srgb, var(--default-color), transparent 92%);
      width: 250px;
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      padding: 24px 16px;
      overflow-y: auto;
    }

    .admin-sidebar .brand {
      font-size: 20px;
      font-weight: 700;
      color: var(--accent-color);
      text-decoration: none;
      display: block;
      margin-bottom: 32px;
    }

    .admin-sidebar .nav-link {
      color: var(--default-color);
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: 0.2s;
      text-decoration: none;
    }

    .admin-sidebar .nav-link:hover,
    .admin-sidebar .nav-link.active {
      background: color-mix(in srgb, var(--accent-color), transparent 85%);
      color: var(--accent-color);
    }

    .admin-sidebar .nav-link i {
      font-size: 18px;
    }

    .admin-main {
      margin-left: 250px;
      padding: 32px;
    }

    .admin-main h1 {
      font-size: 28px;
      font-weight: 600;
      margin-bottom: 24px;
    }

    .stat-card {
      background: var(--surface-color);
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 92%);
      border-radius: 12px;
      padding: 24px;
    }

    .stat-card .number {
      font-size: 32px;
      font-weight: 700;
      color: var(--accent-color);
    }

    .stat-card .label {
      font-size: 14px;
      color: color-mix(in srgb, var(--default-color), transparent 30%);
    }

    .table {
      color: var(--default-color);
      font-size: 14px;
    }

    .table th {
      border-color: color-mix(in srgb, var(--default-color), transparent 88%);
      font-weight: 600;
    }

    .table td {
      border-color: color-mix(in srgb, var(--default-color), transparent 92%);
      vertical-align: middle;
    }

    .badge-featured {
      background: var(--accent-color);
      color: var(--contrast-color);
      font-size: 11px;
      padding: 3px 8px;
      border-radius: 4px;
    }

    .btn-sm {
      padding: 4px 12px;
      font-size: 13px;
      border-radius: 6px;
    }

    /* Page entry animation */
    @keyframes fadeSlideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .admin-main>* {
      animation: fadeSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
      opacity: 0;
    }

    .admin-main>*:nth-child(1) {
      animation-delay: 0.05s;
    }

    .admin-main>*:nth-child(2) {
      animation-delay: 0.1s;
    }

    .admin-main>*:nth-child(3) {
      animation-delay: 0.15s;
    }

    .admin-main>*:nth-child(4) {
      animation-delay: 0.2s;
    }

    .admin-main>*:nth-child(5) {
      animation-delay: 0.25s;
    }

    .admin-main>*:nth-child(6) {
      animation-delay: 0.3s;
    }

    /* Sidebar nav link active glow */
    .admin-sidebar .nav-link.active {
      position: relative;
      overflow: hidden;
    }

    .admin-sidebar .nav-link.active::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--accent-color), transparent 70%), transparent);
      animation: shimmer 2.5s ease-in-out infinite;
    }

    @keyframes shimmer {
      0% {
        transform: translateX(-100%);
      }

      100% {
        transform: translateX(100%);
      }
    }

    /* Mobile responsive */
    .sidebar-toggle {
      display: none;
      position: fixed;
      top: 12px;
      left: 12px;
      z-index: 1060;
      background: var(--surface-color);
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 85%);
      border-radius: 8px;
      padding: 8px 10px;
      cursor: pointer;
      font-size: 22px;
      color: var(--default-color);
      line-height: 1;
    }

    .sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1040;
      background: rgba(0, 0, 0, 0.4);
    }

    .sidebar-overlay.show {
      display: block;
    }

    @media (max-width: 768px) {
      .sidebar-toggle {
        display: block;
      }

      .admin-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 1050;
      }

      .admin-sidebar.open {
        transform: translateX(0);
      }

      .admin-main {
        margin-left: 0;
        padding: 70px 16px 24px;
      }

      .stat-card .number {
        font-size: 26px;
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
  <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
  <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
  <div class="admin-sidebar">
    <a href="index.php" class="brand"><i class="bi bi-code-slash"></i> Portfolio</a>
    <nav>
      <a href="index.php" class="nav-link active"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="projects.php" class="nav-link"><i class="bi bi-folder"></i> Projects</a>
      <a href="messages.php" class="nav-link"><i class="bi bi-envelope"></i> Messages</a>
      <a href="../index.php" class="nav-link"><i class="bi bi-box-arrow-up-right"></i> View Site</a>
      <form action="../logout.php" method="post"><?= csrf_field() ?><button type="submit" class="nav-link border-0 bg-transparent w-100"><i class="bi bi-box-arrow-right"></i> Sign Out</button></form>
    </nav>
  </div>

  <div class="admin-main">
    <h1>Dashboard</h1>

    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="stat-card">
          <div class="number"><?= $projectCount ?></div>
          <div class="label">Total Projects</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-card">
          <div class="number"><?= $featuredCount ?></div>
          <div class="label">Featured</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-card">
          <div class="number"><?= $messageCount ?></div>
          <div class="label">Unread Messages</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-card">
          <div class="number"><?= $userCount ?></div>
          <div class="label">Users</div>
        </div>
      </div>
    </div>

    <div class="stat-card p-0 overflow-hidden">
      <div class="p-3" style="border-bottom:1px solid color-mix(in srgb, var(--default-color), transparent 92%);">
        <strong>Recent Projects</strong>
      </div>
      <div class="p-3 table-responsive">
        <?php if (empty($recentProjects)): ?>
          <p class="mb-0" style="color:color-mix(in srgb, var(--default-color), transparent 40%);">No projects yet. <a
              href="projects-add.php" style="color:var(--accent-color);">Add your first project</a>.</p>
        <?php else: ?>
          <table class="table table-borderless mb-0">
            <thead>
              <tr>
                <th>Title</th>
                <th>Category</th>
                <th>Featured</th>
                <th>Date</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentProjects as $p): ?>
                <tr>
                  <td><?= htmlspecialchars($p['title']) ?></td>
                  <td><?= htmlspecialchars($p['category']) ?></td>
                  <td><?= $p['featured'] ? '<span class="badge-featured">Featured</span>' : '—' ?></td>
                  <td><?= $p['date'] ? date('M j, Y', strtotime($p['date'])) : '—' ?></td>
                  <td><a href="projects-edit.php?id=<?= $p['id'] ?>" class="btn btn-outline-light btn-sm">Edit</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>
