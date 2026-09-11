<?php
$statusCode = http_response_code();
if ($statusCode < 400) {
    $statusCode = 500;
    http_response_code($statusCode);
}
$temporarilyUnavailable = $statusCode === 503;
$pageTitle = $temporarilyUnavailable ? 'Temporarily unavailable — Kennedy Cheroben' : 'Something went wrong — Kennedy Cheroben';
$pageDescription = $temporarilyUnavailable ? 'This part of the site is temporarily unavailable.' : 'The site encountered an unexpected error.';
$pagePath = '500.php';
$bodyClass = 'error-page';
require __DIR__ . '/includes/head.php';
$activePage = '';
require __DIR__ . '/includes/header.php';
?>
<main id="main-content"><section class="page-hero shell"><p class="eyebrow"><?= $temporarilyUnavailable ? '503 / Temporarily unavailable' : '500 / Server error' ?></p><h1><?= $temporarilyUnavailable ? 'This page needs a service that is currently <em>offline.</em>' : 'Something didn’t work as <em>expected.</em>' ?></h1><p class="page-lead">Please try again shortly. Technical details have not been exposed.</p><a class="button" href="<?= e(site_url('index.php')) ?>">Return home</a></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
