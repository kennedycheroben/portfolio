<?php
declare(strict_types=1);
$adminActiveNav ??= '';
$unreadCount = $unreadCount ?? ($pdo instanceof PDO ? (int) $pdo->query('SELECT COUNT(*) FROM contacts WHERE is_read = 0')->fetchColumn() : 0);
?>
<button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle navigation" type="button">
  <i class="bi bi-list"></i>
</button>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<aside class="admin-sidebar" aria-label="Admin sidebar">
  <a href="index.php" class="brand">
    <i class="bi bi-code-slash"></i>
    <span>Portfolio Admin</span>
  </a>

  <nav class="nav" aria-label="Admin navigation">
    <a href="index.php" class="nav-link <?= $adminActiveNav === 'dashboard' ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i>
      <span>Dashboard</span>
    </a>
    <a href="projects.php" class="nav-link <?= $adminActiveNav === 'projects' ? 'active' : '' ?>">
      <i class="bi bi-folder"></i>
      <span>Projects</span>
    </a>
    <a href="messages.php" class="nav-link <?= $adminActiveNav === 'messages' ? 'active' : '' ?>">
      <i class="bi bi-envelope"></i>
      <span>Messages</span>
      <?php if ($unreadCount > 0): ?>
        <span class="badge-unread ms-auto"><?= $unreadCount ?></span>
      <?php endif; ?>
    </a>
    <a href="../index.php" target="_blank" rel="noopener noreferrer" class="nav-link">
      <i class="bi bi-box-arrow-up-right"></i>
      <span>View Live Site</span>
    </a>

    <div class="admin-sidebar-tools">
      <!-- Theme Toggle -->
      <button type="button" class="theme-toggle-btn" id="adminThemeToggle" onclick="toggleAdminTheme()" aria-label="Toggle light or dark theme">
        <i class="bi bi-moon-stars-fill" id="themeToggleIcon" aria-hidden="true"></i>
        <span id="themeToggleLabel">Theme: Dark</span>
      </button>

      <div class="sign-out">
        <form action="../logout.php" method="post">
          <?= csrf_field() ?>
          <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start" style="cursor: pointer;">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sign Out</span>
          </button>
        </form>
      </div>
    </div>
  </nav>
</aside>
