<?php
declare(strict_types=1);

define('DB_OPTIONAL', true);
require_once __DIR__ . '/includes/db.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 1;
$project = null;
if ($pdo instanceof PDO) {
    try {
        $statement = $pdo->prepare('SELECT id, title, description, category, image_url, project_url, client, date FROM projects WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $project = $statement->fetch();
    } catch (PDOException $exception) {
        error_log('Portfolio project detail failed: ' . $exception->getMessage());
    }
}
if (!$project) {
    $project = [
        'id' => $id,
        'title' => 'Mercy Idaya',
        'description' => 'A clean visual identity, digital presence and responsive web application built with care.',
        'category' => 'ui-ux',
        'image_url' => 'assets/img/portfolio/jdm_logo.webp',
        'project_url' => 'https://jdmkenya.com',
        'client' => 'Mercy Idaya',
        'date' => '2026-01-01'
    ];
}
$pageTitle = $project['title'] . ' — Case Study';
$pageDescription = text_excerpt((string) $project['description'], 155);
$pagePath = 'portfolio-details.php?id=' . $id;
$bodyClass = 'project-page';
require __DIR__ . '/includes/head.php';
$activePage = 'portfolio';
require __DIR__ . '/includes/header.php';
$projectUrl = safe_external_url($project['project_url'] ?? null);
?>
<main id="main-content">
  <header class="project-hero shell"><a class="back-link" href="portfolio.php"><i class="bi bi-arrow-left"></i> All work</a><p class="eyebrow"><?= e(ucwords(str_replace('-', ' ', (string) $project['category']))) ?></p><h1><?= e($project['title']) ?></h1><div class="project-facts"><?php if ($project['client']): ?><div><span>Client</span><strong><?= e($project['client']) ?></strong></div><?php endif; ?><?php if ($project['date']): ?><div><span>Date</span><strong><?= e(date('Y', strtotime((string) $project['date']))) ?></strong></div><?php endif; ?><?php if ($projectUrl): ?><a class="button" href="<?= e($projectUrl) ?>" target="_blank" rel="noopener noreferrer">Visit project <i class="bi bi-arrow-up-right"></i></a><?php endif; ?></div></header>
  <section class="shell project-visual-container">
    <div class="project-visual-card">
      <div class="project-visual-canvas">
        <img src="<?= e(project_image_url($project['image_url'] ?? null)) ?>" width="1400" height="900" alt="<?= e($project['title']) ?> project presentation" onerror="this.onerror=null;this.src='<?= e(site_url('assets/img/cheroben_logo.webp')) ?>';">
      </div>
    </div>
  </section>
  <section class="section-pad"><div class="shell project-narrative"><p class="section-index">Project overview</p><div><h2>The recorded brief</h2><p class="large-copy"><?= nl2br(e($project['description'])) ?></p><p class="content-note">More detail on the challenge, process, stack and verified results can be added from the project dashboard when available.</p></div></div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
