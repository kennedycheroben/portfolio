<?php
define('DB_OPTIONAL', true);
require_once __DIR__ . '/includes/db.php';
$pageTitle = 'Selected Work — Kennedy Cheroben';
$pageDescription = 'Selected web development, product design and UI/UX work by Kennedy Cheroben.';
$pagePath = 'portfolio.php';
$bodyClass = 'portfolio-page';
$projects = [];
if ($pdo instanceof PDO) {
    try {
        $projects = $pdo->query('SELECT id, title, description, category, image_url, project_url, client, date, featured FROM projects ORDER BY featured DESC, created_at DESC')->fetchAll();
    } catch (PDOException $exception) {
        error_log('Portfolio project listing failed: ' . $exception->getMessage());
    }
}

if (!$projects) {
    $projects = [
        ['id' => null, 'title' => 'JDM Kenya', 'description' => 'A responsive church website with a practical backend and clear content structure.', 'category' => 'web-design', 'image_url' => 'assets/img/portfolio/jdm_logo.webp', 'project_url' => 'https://jdmkenya.com', 'client' => '', 'date' => null, 'featured' => 1],
        ['id' => null, 'title' => 'MediaFusion', 'description' => 'A social media content management product, formerly presented as Unify Social.', 'category' => 'software-development', 'image_url' => 'assets/img/portfolio/unifysocial.webp', 'project_url' => '', 'client' => '', 'date' => null, 'featured' => 1],
        ['id' => null, 'title' => 'BizIntel', 'description' => 'A business management and data analytics platform concept.', 'category' => 'software-development', 'image_url' => 'assets/img/portfolio/bizintel.webp', 'project_url' => '', 'client' => '', 'date' => null, 'featured' => 1],
    ];
}
require __DIR__ . '/includes/head.php';
$activePage = 'portfolio';
require __DIR__ . '/includes/header.php';
?>
<main id="main-content">
  <header class="page-hero shell"><p class="eyebrow">Selected work</p><h1>Digital products shaped with <em>purpose.</em></h1><p class="page-lead">A selection of design and development work. Filter the collection or open a project for the recorded details.</p></header>
  <section class="section-pad"><div class="shell filter-bar" role="group" aria-label="Filter projects"><button type="button" class="filter-button active" data-filter="all" aria-pressed="true">All</button><?php foreach (array_unique(array_filter(array_column($projects, 'category'))) as $category): ?><button type="button" class="filter-button" data-filter="<?= e($category) ?>" aria-pressed="false"><?= e(ucwords(str_replace('-', ' ', $category))) ?></button><?php endforeach; ?></div>
    <div class="shell portfolio-grid" data-project-grid>
    <?php foreach ($projects as $project):
        $image = project_image_url($project['image_url'] ?? null);
        $detailUrl = $project['id'] ? 'portfolio-details.php?id=' . (int) $project['id'] : safe_external_url($project['project_url'] ?? null);
    ?>
      <article class="work-card reveal" data-category="<?= e($project['category']) ?>">
        <?php if ($detailUrl): ?><a class="work-card-image" href="<?= e($detailUrl) ?>" <?= str_starts_with($detailUrl, 'http') ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?php else: ?><div class="work-card-image"><?php endif; ?>
          <img src="<?= e($image) ?>" width="900" height="680" loading="lazy" alt="<?= e($project['title']) ?> project preview">
          <?php if ($detailUrl): ?><span><i class="bi bi-arrow-up-right"></i></span><?php endif; ?>
        <?= $detailUrl ? '</a>' : '</div>' ?>
        <div class="work-card-copy"><p class="eyebrow"><?= e(ucwords(str_replace('-', ' ', (string) $project['category']))) ?></p><h2><?php if ($detailUrl): ?><a href="<?= e($detailUrl) ?>" <?= str_starts_with($detailUrl, 'http') ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= e($project['title']) ?></a><?php else: ?><?= e($project['title']) ?><?php endif; ?></h2><p><?= e(text_excerpt((string) $project['description'], 180)) ?></p></div>
      </article>
    <?php endforeach; ?>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
