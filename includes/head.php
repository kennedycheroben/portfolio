<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
$pageTitle ??= 'Kennedy Cheroben — Full-Stack Developer & UI/UX Designer';
$pageDescription ??= 'Kennedy Cheroben designs and builds thoughtful digital products from Nairobi, Kenya.';
$pagePath ??= basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$bodyClass ??= '';
$canonical = site_url($pagePath);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDescription) ?>">
  <meta name="theme-color" content="#09090b">
  <link rel="canonical" href="<?= e($canonical) ?>">
  <meta property="og:type" content="website"><meta property="og:title" content="<?= e($pageTitle) ?>"><meta property="og:description" content="<?= e($pageDescription) ?>"><meta property="og:url" content="<?= e($canonical) ?>"><meta property="og:image" content="<?= e(site_url('assets/img/hero-bg.webp')) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="icon" type="image/webp" href="<?= e(site_url('assets/img/cheroben_logo.webp')) ?>">
  <link rel="apple-touch-icon" href="<?= e(site_url('assets/img/cheroben_logo.webp')) ?>">
  <script>document.documentElement.classList.add('js')</script>
  <link rel="stylesheet" href="<?= e(site_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(site_url('assets/css/main.css?v=20260911_2')) ?>">
<?php if (!str_starts_with($pagePath, 'admin/')): ?>
  <link rel="preload" as="image" type="image/webp" href="<?= e(site_url('assets/img/hero-video-poster.webp')) ?>">
  <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'Person', 'name' => 'Kennedy Cheroben', 'url' => site_url(''), 'jobTitle' => 'Full-Stack Developer and UI/UX Designer', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Nairobi', 'addressCountry' => 'KE']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
</head>
<body id="top" class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
