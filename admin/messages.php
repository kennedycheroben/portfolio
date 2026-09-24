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
  <link href="../assets/css/main.css?v=20260911_2" rel="stylesheet">
  <style>
    .admin-body { background: var(--background-color); min-height: 100vh; }
    .admin-sidebar { background: var(--surface-color); border-right: 1px solid color-mix(in srgb, var(--default-color), transparent 92%); width: 250px; position: fixed; top: 0; left: 0; height: 100vh; padding: 24px 16px; overflow-y: auto; }
    .admin-sidebar .brand { font-size: 20px; font-weight: 700; color: var(--accent-color); text-decoration: none; display: block; margin-bottom: 32px; }
    .admin-sidebar .nav-link { color: var(--default-color); padding: 10px 14px; border-radius: 8px; font-size: 14px; display: flex; align-items: center; gap: 10px; transition: 0.2s; text-decoration: none; }
    .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link.active { background: color-mix(in srgb, var(--accent-color), transparent 85%); color: var(--accent-color); }
    .admin-sidebar .nav-link i { font-size: 18px; }
    .admin-main { margin-left: 250px; padding: 32px; }
    .admin-main h1 { font-size: 28px; font-weight: 600; margin-bottom: 24px; }
    .msg-card { background: var(--surface-color); border: 1px solid color-mix(in srgb, var(--default-color), transparent 92%); border-radius: 12px; padding: 20px; margin-bottom: 12px; transition: 0.2s; }
    .msg-card:hover { border-color: color-mix(in srgb, var(--accent-color), transparent 60%); }
    .msg-card.unread { border-left: 3px solid var(--accent-color); }
    .msg-card .meta { font-size: 13px; color: color-mix(in srgb, var(--default-color), transparent 35%); }
    .msg-card .subject { font-weight: 600; font-size: 16px; }
    .msg-card .message-text { font-size: 14px; margin-top: 8px; color: color-mix(in srgb, var(--default-color), transparent 15%); }
    .badge-unread { background: var(--accent-color); color: var(--contrast-color); font-size: 11px; padding: 2px 8px; border-radius: 10px; }
    .alert { border-radius: 8px; font-size: 14px; }
    .btn-sm { padding: 4px 12px; font-size: 13px; border-radius: 6px; }

    /* Page entry animation */
    @keyframes fadeSlideUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
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
      background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--accent-color), transparent 70%), transparent);
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
      background: rgba(0,0,0,0.4);
    }
    .sidebar-overlay.show { display: block; }
    @media (max-width: 768px) {
      .sidebar-toggle { display: block; }
      .admin-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; z-index: 1050; }
      .admin-sidebar.open { transform: translateX(0); }
      .admin-main { margin-left: 0; padding: 70px 16px 24px; }
    }
  </style>
  <script>function toggleSidebar(){document.querySelector('.admin-sidebar').classList.toggle('open');document.querySelector('.sidebar-overlay').classList.toggle('show')}</script>
</head>
<body class="admin-body">
  <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
  <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
  <div class="admin-sidebar">
    <a href="index.php" class="brand"><i class="bi bi-code-slash"></i> Portfolio</a>
    <nav>
      <a href="index.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="projects.php" class="nav-link"><i class="bi bi-folder"></i> Projects</a>
      <a href="messages.php" class="nav-link active">
        <i class="bi bi-envelope"></i> Messages
        <?php if ($unreadCount > 0): ?>
          <span class="badge-unread ms-auto"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>
      <a href="../index.php" class="nav-link"><i class="bi bi-box-arrow-up-right"></i> View Site</a>
      <form action="../logout.php" method="post"><?= csrf_field() ?><button type="submit" class="nav-link border-0 bg-transparent w-100"><i class="bi bi-box-arrow-right"></i> Sign Out</button></form>
    </nav>
  </div>

  <div class="admin-main">
    <h1>
      Messages
      <?php if ($unreadCount > 0): ?>
        <span class="badge-unread" style="font-size:14px;padding:4px 12px;vertical-align:middle;"><?= $unreadCount ?> unread</span>
      <?php endif; ?>
    </h1>

    <?php if (isset($_GET['deleted'])): ?>
      <div class="alert alert-success">Message deleted.</div>
    <?php endif; ?>

    <?php if (empty($messages)): ?>
      <div class="text-center py-5" style="color:color-mix(in srgb, var(--default-color), transparent 40%);">
        <i class="bi bi-inbox" style="font-size:48px;display:block;margin-bottom:16px;"></i>
        <p>No messages yet.</p>
      </div>
    <?php else: ?>
      <?php foreach ($messages as $m): ?>
      <div class="msg-card <?= !$m['is_read'] ? 'unread' : '' ?>">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="subject"><?= htmlspecialchars($m['subject']) ?></div>
            <div class="meta">
              <strong><?= htmlspecialchars($m['name']) ?></strong>
              &lt;<?= htmlspecialchars($m['email']) ?>&gt;
              &middot; <?= date('M j, Y g:i A', strtotime($m['created_at'])) ?>
            </div>
          </div>
          <div class="d-flex gap-1">
            <?php if (!$m['is_read']): ?>
              <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="read"><button class="btn btn-outline-light btn-sm" title="Mark as read" aria-label="Mark message as read"><i class="bi bi-envelope-open"></i></button></form>
            <?php else: ?>
              <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="unread"><button class="btn btn-outline-light btn-sm" title="Mark as unread" aria-label="Mark message as unread"><i class="bi bi-envelope"></i></button></form>
            <?php endif; ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this message?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger btn-sm" title="Delete" aria-label="Delete message"><i class="bi bi-trash"></i></button></form>
          </div>
        </div>
        <div class="message-text"><?= nl2br(e($m['message'])) ?></div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($pageCount > 1): ?><nav aria-label="Message pages" class="d-flex gap-2 mt-4"><?php for ($number = 1; $number <= $pageCount; $number++): ?><a class="btn btn-sm <?= $number === $page ? 'btn-light' : 'btn-outline-light' ?>" href="?page=<?= $number ?>"><?= $number ?></a><?php endfor; ?></nav><?php endif; ?>
  </div>
</body>
</html>
