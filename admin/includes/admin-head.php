<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
$adminPageTitle = ($adminPageTitle ?? 'Admin Dashboard') . ' — Portfolio Administration';
?>
<!DOCTYPE html>
<html lang="en" data-admin-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($adminPageTitle) ?></title>
  <link rel="icon" type="image/x-icon" href="<?= e(site_url('favicon.ico')) ?>">
  <link rel="icon" type="image/webp" href="<?= e(site_url('assets/img/cheroben_logo.webp')) ?>">
  <link rel="apple-touch-icon" href="<?= e(site_url('assets/img/cheroben_logo.webp')) ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(site_url('assets/css/main.css?v=20261013_1')) ?>" rel="stylesheet">
  <link href="<?= e(site_url('assets/css/admin.css?v=20261013_1')) ?>" rel="stylesheet">
  <script>
    (function() {
      try {
        var stored = localStorage.getItem('portfolio_admin_theme');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = stored ? stored : (prefersDark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-admin-theme', theme);
      } catch (e) {}
    })();
  </script>
</head>
<body class="admin-body">
