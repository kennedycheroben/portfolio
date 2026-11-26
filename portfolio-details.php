<?php
declare(strict_types=1);

define('DB_OPTIONAL', true);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null) {
    http_response_code(400);
    $pageTitle = 'Invalid project identifier — Kennedy Cheroben';
    $pageDescription = 'The requested project identifier is invalid.';
    $pagePath = 'portfolio-details.php';
    $bodyClass = 'error-page';
    require __DIR__ . '/includes/head.php';
    $activePage = 'portfolio';
    require __DIR__ . '/includes/header.php';
?>
<main id="main-content">
  <section class="page-hero shell" style="text-align: center;">
    <p class="eyebrow">400 / Bad Request</p>
    <h1>Invalid project <em>identifier.</em></h1>
    <p class="page-lead">The project ID must be a valid number.</p>
    <div class="button-row" style="justify-content: center;">
      <a class="button" href="<?= e(site_url('portfolio.php')) ?>">Browse all projects</a>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<?php
    exit;
}

$project = null;
if ($pdo instanceof PDO) {
    try {
        if (isAdmin()) {
            $statement = $pdo->prepare('SELECT * FROM projects WHERE id = ? LIMIT 1');
        } else {
            $statement = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND status = 'published' LIMIT 1");
        }
        $statement->execute([$id]);
        $project = $statement->fetch();
    } catch (PDOException $exception) {
        error_log('Portfolio project detail failed: ' . $exception->getMessage());
    }
}

if (!$project) {
    http_response_code(404);
    $pageTitle = 'Project not found — Kennedy Cheroben';
    $pageDescription = 'The requested project could not be found. It may be in draft, removed or never existed.';
    $pagePath = 'portfolio-details.php?id=' . $id;
    $bodyClass = 'error-page';
    require __DIR__ . '/includes/head.php';
    $activePage = 'portfolio';
    require __DIR__ . '/includes/header.php';
?>
<main id="main-content">
  <section class="page-hero shell" style="text-align: center;">
    <p class="eyebrow">404 / Not Found</p>
    <h1>That project doesn't <em>exist.</em></h1>
    <p class="page-lead">The requested project could not be found. It may be in draft, removed or never existed.</p>
    <div class="button-row" style="justify-content: center;">
      <a class="button" href="<?= e(site_url('portfolio.php')) ?>">Browse all projects</a>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<?php
    exit;
}

$pageTitle = $project['title'] . ' — Case Study | Kennedy Cheroben';
$pageDescription = text_excerpt((string) ($project['problem'] ?: $project['description']), 160);
$pagePath = 'portfolio-details.php?id=' . $id;
$bodyClass = 'project-page';

$rawImage = !empty($project['image_url']) ? (string) $project['image_url'] : 'assets/img/portfolio/portfolio-1.webp';
$canonicalUrl = canonical_url('portfolio-details.php?id=' . (int) $project['id']);
$ogImage = safe_external_url($rawImage) ?: canonical_url($rawImage);

$liveDemoUrl = safe_external_url($project['project_url'] ?? null);
$githubUrl = safe_external_url($project['github_url'] ?? null);
$techList = !empty($project['technologies']) ? array_filter(array_map('trim', explode(',', $project['technologies']))) : [];

// Structured data for this project case study
$projectSchema = [
    '@type' => 'CreativeWork',
    '@id' => canonical_url('portfolio-details.php?id=' . (int) $project['id']),
    'name' => $project['title'],
    'headline' => $project['title'] . ' — Case Study by Kennedy Cheroben',
    'description' => text_excerpt((string) ($project['description'] ?: $project['problem']), 200),
    'author' => ['@id' => canonical_url('#person')],
    'creator' => ['@id' => canonical_url('#person')],
    'dateCreated' => !empty($project['date']) ? date('Y-m-d', strtotime((string) $project['date'])) : date('Y-m-d', strtotime((string) $project['created_at'])),
    'genre' => ucwords(str_replace('-', ' ', (string) $project['category'])),
    'image' => safe_external_url($rawImage) ?: canonical_url($rawImage),
    'inLanguage' => 'en-US'
];
if ($liveDemoUrl) {
    $projectSchema['url'] = $liveDemoUrl;
}
if (!empty($techList)) {
    $projectSchema['keywords'] = implode(', ', $techList);
}

require __DIR__ . '/includes/head.php';
$activePage = 'portfolio';
require __DIR__ . '/includes/header.php';
?>
<main id="main-content">
  <?php if (($project['status'] ?? 'published') === 'draft'): ?>
    <div class="shell" style="margin-top: 24px;">
      <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4); color: #fbbf24; padding: 12px 18px; border-radius: var(--radius-sm); font-size: var(--font-size-step-0); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <span><i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i> <strong>Draft Preview:</strong> This project is currently unpublished and only visible to administrators.</span>
        <a href="admin/projects-edit.php?id=<?= (int) $project['id'] ?>" class="button button-small" style="padding: 4px 12px; font-size: 0.8rem;">Edit in Admin</a>
      </div>
    </div>
  <?php endif; ?>

  <header class="project-hero shell">
    <a class="back-link" href="<?= e(site_url('portfolio.php')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to all work</a>
    <p class="eyebrow"><?= e(ucwords(str_replace('-', ' ', (string) $project['category']))) ?></p>
    <h1><?= e($project['title']) ?></h1>
    <div class="project-facts">
      <?php if (!empty($project['client'])): ?>
        <div><span>Client / Context</span><strong><?= e($project['client']) ?></strong></div>
      <?php endif; ?>
      <?php if (!empty($project['date'])): ?>
        <div><span>Timeline</span><strong><?= e(date('F Y', strtotime((string) $project['date']))) ?></strong></div>
      <?php endif; ?>
      <?php if ($liveDemoUrl): ?>
        <a class="button button-small" href="<?= e($liveDemoUrl) ?>" target="_blank" rel="noopener noreferrer">Live Demo <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
      <?php endif; ?>
      <?php if ($githubUrl): ?>
        <a class="button button-small button-outline" href="<?= e($githubUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="GitHub Repository for <?= e($project['title']) ?>"><i class="bi bi-github" aria-hidden="true"></i> View Code</a>
      <?php endif; ?>
    </div>
  </header>

  <section class="shell project-visual-container">
    <div class="project-visual-card">
      <div class="project-visual-canvas">
        <img src="<?= e(project_image_url($project['image_url'] ?? null)) ?>" width="1400" height="900" alt="<?= e($project['title']) ?> project presentation" onerror="this.onerror=null;this.src='<?= e(site_url('assets/img/cheroben_logo.webp')) ?>';">
      </div>
    </div>
  </section>

  <!-- Case study content sections -->
  <section class="section-pad">
    <div class="shell">
      <?php if (!empty($project['description'])): ?>
        <div class="case-study-grid">
          <p class="case-study-title">Overview</p>
          <div class="case-study-body">
            <h2>Project summary</h2>
            <p><?= nl2br(e($project['description'])) ?></p>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($project['problem'])): ?>
        <div class="case-study-grid">
          <p class="case-study-title">The Challenge</p>
          <div class="case-study-body">
            <h2>Problem & purpose</h2>
            <p><?= nl2br(e($project['problem'])) ?></p>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($project['contribution'])): ?>
        <div class="case-study-grid">
          <p class="case-study-title">Contribution</p>
          <div class="case-study-body">
            <h2>My role & responsibilities</h2>
            <p><?= nl2br(e($project['contribution'])) ?></p>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($project['features'])): ?>
        <div class="case-study-grid">
          <p class="case-study-title">Key Features</p>
          <div class="case-study-body">
            <h2>Architecture & capabilities</h2>
            <p><?= nl2br(e($project['features'])) ?></p>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($techList)): ?>
        <div class="case-study-grid">
          <p class="case-study-title">Technology Stack</p>
          <div class="case-study-body">
            <h2>Tools & frameworks used</h2>
            <div class="project-tech-pills" style="margin-top: 8px;">
              <?php foreach ($techList as $tech): ?>
                <span class="tech-chip" style="font-size: 0.85rem; padding: 6px 14px;"><?= e($tech) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($project['outcomes'])): ?>
        <div class="case-study-grid">
          <p class="case-study-title">Outcomes</p>
          <div class="case-study-body">
            <h2>Results & key takeaways</h2>
            <p><?= nl2br(e($project['outcomes'])) ?></p>
          </div>
        </div>
      <?php endif; ?>

      <div class="case-study-grid" style="border-bottom: 1px solid var(--line-soft);">
        <p class="case-study-title">Next Steps</p>
        <div class="case-study-body">
          <h2>Have a similar project to build?</h2>
          <p>I collaborate with businesses and teams on full-stack web applications, clean responsive websites and user-centered UI/UX systems.</p>
          <div class="project-actions" style="margin-top: 20px;">
            <a class="button" href="<?= e(site_url('contact.php')) ?>">Discuss a Project <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            <a class="button button-outline" href="https://wa.me/254792399815?text=<?= urlencode('Hello Kennedy, I saw your ' . $project['title'] . ' project and would like to discuss working together.') ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp" aria-hidden="true"></i> Chat on WhatsApp</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>