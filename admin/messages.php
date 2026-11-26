<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';

if (!$pdo instanceof PDO) {
    http_response_code(503);
    exit('Database connection unavailable.');
}

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
$unreadCount = (int) $pdo->query('SELECT COUNT(*) FROM contacts WHERE is_read = 0')->fetchColumn();

$adminPageTitle = 'Enquiry Messages';
$adminActiveNav = 'messages';
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="mb-0">
      Enquiry Messages
      <?php if ($unreadCount > 0): ?>
        <span class="badge-unread ms-2" style="font-size: 0.875rem; vertical-align: middle;"><?= $unreadCount ?> unread</span>
      <?php endif; ?>
    </h1>
  </div>

  <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success" role="status"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i> Message deleted successfully.</div>
  <?php endif; ?>

  <?php if (empty($messages)): ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="bi bi-inbox" style="font-size: 2.5rem; color: var(--admin-text-dim); display: block; margin-bottom: 12px;"></i>
        <p class="mb-0" style="color: var(--admin-text-muted);">No enquiry messages received yet.</p>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($messages as $m): ?>
      <article class="msg-card <?= empty($m['is_read']) ? 'unread' : '' ?>">
        <div class="msg-header">
          <div>
            <div class="msg-subject"><?= e($m['subject']) ?></div>
            <div class="msg-meta-info">
              <strong><?= e($m['name']) ?></strong> &lt;<a href="mailto:<?= e($m['email']) ?>" style="color: var(--admin-accent); text-decoration: none;"><?= e($m['email']) ?></a>&gt;
              &middot; <?= date('M j, Y g:i A', strtotime((string) $m['created_at'])) ?>
            </div>
          </div>
          <div class="msg-actions">
            <?php if (empty($m['is_read'])): ?>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <input type="hidden" name="action" value="read">
                <button type="submit" class="btn-admin-view btn-sm" title="Mark as read" aria-label="Mark message from <?= e($m['name']) ?> as read">
                  <i class="bi bi-envelope-open me-1" aria-hidden="true"></i> Mark Read
                </button>
              </form>
            <?php else: ?>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <input type="hidden" name="action" value="unread">
                <button type="submit" class="btn-admin-view btn-sm" title="Mark as unread" aria-label="Mark message from <?= e($m['name']) ?> as unread">
                  <i class="bi bi-envelope me-1" aria-hidden="true"></i> Mark Unread
                </button>
              </form>
            <?php endif; ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete this message?')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="btn-admin-delete btn-sm" title="Delete" aria-label="Delete message from <?= e($m['name']) ?>">
                <i class="bi bi-trash3" aria-hidden="true"></i>
              </button>
            </form>
          </div>
        </div>
        <div class="msg-body"><?= nl2br(e($m['message'])) ?></div>
      </article>
    <?php endforeach; ?>

    <?php if ($pageCount > 1): ?>
      <nav aria-label="Message pages" class="pagination py-4">
        <?php for ($number = 1; $number <= $pageCount; $number++): ?>
          <a class="btn btn-sm <?= $number === $page ? 'btn-accent' : 'btn-admin-view' ?>" href="?page=<?= $number ?>"><?= $number ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>