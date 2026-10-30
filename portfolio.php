<?php
define('DB_OPTIONAL', true);
require_once __DIR__ . '/includes/db.php';
$pageTitle = 'Selected Work & Case Studies — Kennedy Cheroben';
$pageDescription = 'Portfolio of web applications, custom PHP platforms, responsive websites and UI/UX design case studies by Kennedy Cheroben in Nairobi, Kenya.';
$pagePath = 'portfolio.php';
$bodyClass = 'portfolio-page';
$projects = [];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT id, title, description, category, technologies, image_url, project_url, github_url, client, date, featured FROM projects WHERE status = 'published' ORDER BY featured DESC, created_at DESC");
        $projects = $stmt ? $stmt->fetchAll() : [];
    } catch (PDOException $exception) {
        error_log('Portfolio project listing failed: ' . $exception->getMessage());
    }
}
require __DIR__ . '/includes/head.php';
$activePage = 'portfolio';
require __DIR__ . '/includes/header.php';
?>
<main id="main-content">
  <header class="page-hero shell">
    <p class="eyebrow">Selected work</p>
    <h1>Digital products shaped with <em>purpose.</em></h1>
    <p class="page-lead">A selection of web development, product engineering and interface design case studies. Filter the collection or open any project to read the detailed case study.</p>
  </header>
  <section class="section-pad">
    <?php if (!empty($projects)): ?>
      <div class="shell filter-bar" role="group" aria-label="Filter projects">
        <button type="button" class="filter-button active" data-filter="all" aria-pressed="true">All Work</button>
        <?php foreach (array_unique(array_filter(array_column($projects, 'category'))) as $category): ?>
          <button type="button" class="filter-button" data-filter="<?= e($category) ?>" aria-pressed="false"><?= e(ucwords(str_replace('-', ' ', $category))) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="shell portfolio-grid" data-project-grid>
        <?php foreach ($projects as $project):
            $image = project_image_url($project['image_url'] ?? null);
            $detailUrl = 'portfolio-details.php?id=' . (int) $project['id'];
            $liveDemoUrl = safe_external_url($project['project_url'] ?? null);
            $githubUrl = safe_external_url($project['github_url'] ?? null);
            $techList = !empty($project['technologies']) ? array_filter(array_map('trim', explode(',', $project['technologies']))) : [];
        ?>
          <article class="work-card reveal" data-category="<?= e($project['category']) ?>">
            <a class="work-card-image" href="<?= e($detailUrl) ?>" aria-label="View case study for <?= e($project['title']) ?>">
              <img src="<?= e($image) ?>" width="900" height="680" loading="lazy" alt="<?= e($project['title']) ?> project preview" onerror="this.onerror=null;this.src='<?= e(site_url('assets/img/cheroben_logo.webp')) ?>';">
              <span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></span>
            </a>
            <div class="work-card-copy">
              <span class="project-category"><?= e(ucwords(str_replace('-', ' ', (string) $project['category']))) ?></span>
              <h2><a href="<?= e($detailUrl) ?>"><?= e($project['title']) ?></a></h2>
              <p><?= e(text_excerpt((string) $project['description'], 160)) ?></p>
              <?php if (!empty($techList)): ?>
                <div class="project-tech-pills">
                  <?php foreach (array_slice($techList, 0, 4) as $tech): ?>
                    <span class="tech-chip"><?= e($tech) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <div class="project-actions">
                <a class="button button-small" href="<?= e($detailUrl) ?>">View Case Study <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                <?php if ($liveDemoUrl): ?>
                  <a class="button button-small button-outline" href="<?= e($liveDemoUrl) ?>" target="_blank" rel="noopener noreferrer">Live Demo <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                <?php endif; ?>
                <?php if ($githubUrl): ?>
                  <a class="button button-small button-outline" href="<?= e($githubUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="GitHub Repository for <?= e($project['title']) ?>"><i class="bi bi-github" aria-hidden="true"></i> Code</a>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="shell empty-state" style="text-align: center; padding: 60px 20px;">
        <p class="large-copy" style="color: var(--text); margin-bottom: 12px;">New case studies will be published soon.</p>
        <p style="color: var(--text-dim); max-width: 520px; margin-inline: auto; margin-bottom: 24px;">Project entries are managed directly through the administration dashboard. In the meantime, discover services or discuss an upcoming project.</p>
        <div class="button-row" style="justify-content: center; gap: 14px;">
          <a class="button" href="<?= e(site_url('services.php')) ?>">Explore Services</a>
          <a class="button button-outline" href="<?= e(site_url('contact.php')) ?>">Start a Project</a>
        </div>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>