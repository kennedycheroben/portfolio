<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
  $action = input_string($_POST, 'action');
  if (!$id || !in_array($action, ['read', 'unread', 'delete'], true)) {
    http_response_code(400);
    exit('Invalid action.');
  }
  if ($action === 'delete') {
    $pdo->prepare('DELETE FROM contacts WHERE id = ?')->execute([$id]);
    redirect('messages.php?deleted=1');
  }
  $stmt = $pdo->prepare('UPDATE contacts SET is_read = ? WHERE id = ?');
  $stmt->execute([$action === 'read' ? 1 : 0, $id]);
  redirect('messages.php');
}

$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 25;
$totalMessages = (int) $pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn();
$pageCount = max(1, (int) ceil($totalMessages / $perPage));
$page = min($page, $pageCount);
$stmt = $pdo->prepare('SELECT * FROM contacts ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll();
$unreadCount = $pdo->query('SELECT COUNT(*) FROM contacts WHERE is_read = 0')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages - Portfolio</title>
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

    .badge-unread {
      background: var(--accent);
      color: var(--contrast-color);
      font-size: var(--font-size-step-0);
      padding: 4px 10px;
      border-radius: 999px;
      font-weight: 600;
      margin-left: 12px;
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

    .msg-card {
      background: var(--glass);
      border: 1px solid var(--glass-border);
      border-radius: var(--radius);
      padding: 24px;
      margin-bottom: 16px;
      transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }

    .msg-card:hover {
      border-color: var(--glass-border-strong);
      box-shadow: var(--glass-shadow-hover);
    }

    .msg-card.unread {
      border-left: 3px solid var(--accent);
    }

    .msg-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 16px;
      margin-bottom: 16px;
    }

    .msg-meta {
      flex: 1;
      min-width: 0;
    }

    .msg-subject {
      font-weight: 600;
      font-size: var(--font-size-step-2);
      margin-bottom: 6px;
      color: var(--text);
    }

    .msg-meta-info {
      font-size: var(--font-size-step-1);
      color: var(--text-dim);
    }

    .msg-meta-info strong {
      color: var(--text);
    }

    .msg-actions {
      display: flex;
      gap: 8px;
      flex-shrink: 0;
    }

    .btn-sm {
      padding: 8px 12px;
      font-size: var(--font-size-step-0);
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

    .msg-body {
      font-size: var(--font-size-step-1);
      line-height: 1.7;
      color: var(--text-dim);
      white-space: pre-wrap;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--text-dim);
    }

    .empty-state i {
      font-size: 48px;
      display: block;
      margin-bottom: 16px;
      opacity: 0.5;
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
      <a href="projects.php" class="nav-link"><i class="bi bi-folder"></i> Projects</a>
      <a href="messages.php" class="nav-link active">
        <i class="bi bi-envelope"></i> Messages
        <?php if ($unreadCount > 0): ?>
          <span class="badge-unread ms-auto"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>
      <a href="../index.php" class="nav-link"><i class="bi bi-box-arrow-up-right"></i> View Site</a>
      <div class="sign-out">
        <form action="../logout.php" method="post"><?= csrf_field() ?><button type="submit" class="nav-link border-0 bg-transparent w-100 text-start"><i class="bi bi-box-arrow-right"></i> Sign Out</button></form>
      </div>
    </nav>
  </div>

  <div class="admin-main">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="mb-0">
        Messages
        <?php if ($unreadCount > 0): ?>
          <span class="badge-unread"><?= $unreadCount ?> unread</span>
        <?php endif; ?>
      </h1>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
      <div class="alert alert-success">Message deleted.</div>
    <?php endif; ?>

    <?php if (empty($messages)): ?>
      <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <p>No messages yet.</p>
      </div>
    <?php else: ?>
      <?php foreach ($messages as $m): ?>
        <div class="msg-card <?= !$m['is_read'] ? 'unread' : '' ?>">
          <div class="msg-header">
            <div class="msg-meta">
              <div class="msg-subject"><?= htmlspecialchars($m['subject']) ?></div>
              <div class="msg-meta-info">
                <strong><?= htmlspecialchars($m['name']) ?></strong>
                <<?= htmlspecialchars($m['email']) ?>>
                &middot; <?= date('M j, Y g:i A', strtotime($m['created_at'])) ?>
              </div>
            </div>
            <div class="msg-actions">
              <?php if (!$m['is_read']): ?>
                <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="read"><button class="btn btn-outline-light btn-sm" title="Mark as read" aria-label="Mark message as read"><i class="bi bi-envelope-open"></i></button></form>
              <?php else: ?>
                <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="unread"><button class="btn btn-outline-light btn-sm" title="Mark as unread" aria-label="Mark message as unread"><i class="bi bi-envelope"></i></button></form>
              <?php endif; ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this message?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger btn-sm" title="Delete" aria-label="Delete message"><i class="bi bi-trash"></i></button></form>
            </div>
          </div>
          <div class="msg-body"><?= nl2br(e($m['message'])) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($pageCount > 1): ?>
      <nav aria-label="Message pages" class="pagination">
        <?php for ($number = 1; $number <= $pageCount; $number++): ?>
          <a class="btn btn-sm <?= $number === $page ? 'btn-accent' : 'btn-outline-light' ?>" href="?page=<?= $number ?>"><?= $number ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  </div>
</body>

</html>