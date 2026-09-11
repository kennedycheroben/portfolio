<?php
http_response_code(404);
$pageTitle = 'Page not found — Kennedy Cheroben';
$pageDescription = 'The requested page could not be found.';
$pagePath = '404.php';
$bodyClass = 'error-page';
require __DIR__ . '/includes/head.php';
$activePage = '';
require __DIR__ . '/includes/header.php';
?>
<main id="main-content"><section class="page-hero shell"><p class="eyebrow">404 / Not found</p><h1>That page has moved or never <em>existed.</em></h1><p class="page-lead">Try the work archive, or head back to the homepage.</p><div class="button-row"><a class="button" href="<?= e(site_url('index.php')) ?>">Go home</a><a class="text-link" href="<?= e(site_url('portfolio.php')) ?>">Browse work <i class="bi bi-arrow-right"></i></a></div></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
