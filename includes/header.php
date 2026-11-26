<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$activePage ??= '';
?>
<header class="site-header" data-site-header>
  <div class="shell nav-shell">
    <a class="brand" href="<?= e(site_url('index.php')) ?>" aria-label="Kennedy Cheroben — home"><span class="brand-mark"><img src="<?= e(site_url('assets/img/cheroben_logo.webp')) ?>" width="36" height="36" alt="" aria-hidden="true"></span><span class="brand-name">Kennedy Cheroben</span></a>
    <button class="menu-toggle" type="button" aria-controls="primary-navigation" aria-expanded="false"><span class="sr-only">Open navigation</span><i class="bi bi-list" aria-hidden="true"></i></button>
    <nav id="primary-navigation" class="primary-navigation" aria-label="Primary navigation">
      <a href="<?= e(site_url('index.php')) ?>" <?= $activePage === 'home' ? 'aria-current="page"' : '' ?>>Home</a>
      <a href="<?= e(site_url('about.php')) ?>" <?= $activePage === 'about' ? 'aria-current="page"' : '' ?>>About</a>
      <a href="<?= e(site_url('services.php')) ?>" <?= $activePage === 'services' ? 'aria-current="page"' : '' ?>>Services</a>
      <a href="<?= e(site_url('portfolio.php')) ?>" <?= $activePage === 'portfolio' ? 'aria-current="page"' : '' ?>>Work</a>
      <a href="<?= e(site_url('contact.php')) ?>" <?= $activePage === 'contact' ? 'aria-current="page"' : '' ?>>Contact</a>
      <?php if (isAdmin()): ?><a href="<?= e(site_url('admin/index.php')) ?>">Dashboard</a><?php endif; ?>
      <a class="button button-small" href="<?= e(site_url('contact.php')) ?>">Start a project</a>
    </nav>
  </div>
</header>
<div class="site-video-background" data-scroll-video-background aria-hidden="true">
  <video class="scroll-video" width="1282" height="720" muted playsinline preload="auto" poster="<?= e(site_url('assets/img/hero-video-poster.webp')) ?>" tabindex="-1">
    <source src="<?= e(site_url('assets/videos/new_bg.webm')) ?>" type="video/webm">
  </video>
  <div class="scroll-video-shade"></div>
</div>
