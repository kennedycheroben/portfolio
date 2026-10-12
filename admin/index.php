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

    .stat-card {
      background: var(--glass);
      border: 1px solid var(--glass-border);
      border-radius: var(--radius);
      padding: 28px 24px;
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      transition: transform var(--transition-base), box-shadow var(--transition-base), border-color var(--transition-base);
    }

    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--glass-shadow-hover);
      border-color: var(--glass-border-strong);
    }

    .stat-card .number {
      font-size: var(--font-size-step-5);
      font-weight: 700;
      color: var(--accent);
      line-height: 1;
      margin-bottom: 8px;
    }

    .stat-card .label {
      font-size: var(--font-size-step-1);
      color: var(--text-dim);
      font-weight: 500;
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

    .btn-accent {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
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
      padding: 24px;
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
      <a href="index.php" class="nav-link active"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="projects.php" class="nav-link"><i class="bi bi-folder"></i> Projects</a>
      <a href="messages.php" class="nav-link"><i class="bi bi-envelope"></i> Messages</a>
      <a href="../index.php" class="nav-link"><i class="bi bi-box-arrow-up-right"></i> View Site</a>
      <div class="sign-out">
        <form action="../logout.php" method="post"><?= csrf_field() ?><button type="submit" class="nav-link border-0 bg-transparent w-100 text-start"><i class="bi bi-box-arrow-right"></i> Sign Out</button></form>
      </div>
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

    <div class="card">
      <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <strong>Recent Projects</strong>
          <a href="projects-add.php" class="btn btn-accent btn-sm"><i class="bi bi-plus-lg"></i> Add Project</a>
        </div>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentProjects)): ?>
          <div class="p-4 text-center">
            <p class="mb-2" style="color: var(--text-dim);">No projects yet.</p>
            <a href="projects-add.php" class="btn btn-accent btn-sm"><i class="bi bi-plus-lg"></i> Add your first project</a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
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
                    <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                    <td><?= htmlspecialchars($p['category']) ?></td>
                    <td><?= $p['featured'] ? '<span class="badge-featured">Featured</span>' : '<span style="color:var(--text-dim);">—</span>' ?></td>
                    <td><?= $p['date'] ? date('M j, Y', strtotime($p['date'])) : '<span style="color:var(--text-dim);">—</span>' ?></td>
                    <td><a href="projects-edit.php?id=<?= $p['id'] ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-pencil"></i> Edit</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="text-center p-3" style="border-top: 1px solid var(--line);">
            <a href="projects.php" class="text-link"><i class="bi bi-arrow-right"></i> View all projects</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>