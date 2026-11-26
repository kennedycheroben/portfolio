<?php
$pageTitle = 'Kennedy Cheroben — Visual Design Specialist & Full-Stack Developer | Nairobi, Kenya';
$pageDescription = 'Kennedy Cheroben is a Visual Design Specialist and Full-Stack Developer in Nairobi, Kenya, crafting secure PHP applications, responsive websites and user-centered UI/UX designs.';
$pagePath = 'index.php';
$bodyClass = 'home-page';

define('DB_OPTIONAL', true);
require_once __DIR__ . '/includes/db.php';

$featuredProjects = [];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT id, title, description, category, technologies, image_url, project_url, github_url, client, date FROM projects WHERE status = 'published' AND featured = 1 ORDER BY created_at DESC LIMIT 3");
        $featuredProjects = $stmt ? $stmt->fetchAll() : [];

        // If no featured projects are explicitly flagged, show recent published projects
        if (empty($featuredProjects)) {
            $stmt = $pdo->query("SELECT id, title, description, category, technologies, image_url, project_url, github_url, client, date FROM projects WHERE status = 'published' ORDER BY created_at DESC LIMIT 3");
            $featuredProjects = $stmt ? $stmt->fetchAll() : [];
        }
    } catch (PDOException $exception) {
        error_log('Featured projects query failed: ' . $exception->getMessage());
    }
}

require __DIR__ . '/includes/head.php';
$activePage = 'home';
require __DIR__ . '/includes/header.php';
?>
<main id="main-content">
  <section class="sequence-hero" aria-labelledby="hero-title">
    <div class="sequence-sticky">
      <div class="shell hero-content">
        <p class="eyebrow">Visual Design Specialist <span>·</span> Full-Stack Developer <span>·</span> Nairobi, Kenya</p>
        <h1 id="hero-title">I design and build digital products that work <em>beautifully.</em></h1>
        <p class="hero-lead">Product-minded design, secure PHP development and careful frontend craft—brought together to create dependable, accessible experiences for real people.</p>
        <div class="button-row">
          <a class="button" href="#selected-work">View my work <i class="bi bi-arrow-down" aria-hidden="true"></i></a>
          <a class="button button-outline" href="contact.php">Start a project <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        </div>
      </div>
      <div class="scroll-cue" aria-hidden="true"><span>Scroll to explore</span><i class="bi bi-arrow-down"></i></div>
    </div>
  </section>

  <section class="intro-section section-pad">
    <div class="shell intro-grid reveal">
      <p class="section-index">01 / Introduction</p>
      <div>
        <h2>Design thinking.<br>Engineering discipline.</h2>
        <p class="large-copy">I'm Kennedy Cheroben, a visual designer and developer based in Nairobi, Kenya. I specialize in turning complex requirements into clear, responsive and maintainable digital products.</p>
      </div>
    </div>
    <div class="shell skills-marquee" aria-label="Core technologies" tabindex="0">
      <div class="skills-marquee-track">
        <div class="skills-marquee-group">
          <span class="skill-pill" data-category="language">PHP 8+</span>
          <span class="skill-pill" data-category="framework">Laravel</span>
          <span class="skill-pill" data-category="language">JavaScript (ES6+)</span>
          <span class="skill-pill" data-category="language">TypeScript</span>
          <span class="skill-pill" data-category="database">MySQL / PDO</span>
          <span class="skill-pill" data-category="design">Figma</span>
          <span class="skill-pill" data-category="design">UI/UX Design</span>
          <span class="skill-pill" data-category="frontend">Responsive CSS3</span>
          <span class="skill-pill" data-category="frontend">Bootstrap 5</span>
          <span class="skill-pill" data-category="frontend">Tailwind CSS</span>
          <span class="skill-pill" data-category="backend">REST APIs</span>
          <span class="skill-pill" data-category="devops">Git & CI/CD</span>
          <span class="skill-pill" data-category="devops">cPanel / Linux</span>
        </div>
        <div class="skills-marquee-group" aria-hidden="true">
          <span class="skill-pill" data-category="language">PHP 8+</span>
          <span class="skill-pill" data-category="framework">Laravel</span>
          <span class="skill-pill" data-category="language">JavaScript (ES6+)</span>
          <span class="skill-pill" data-category="language">TypeScript</span>
          <span class="skill-pill" data-category="database">MySQL / PDO</span>
          <span class="skill-pill" data-category="design">Figma</span>
          <span class="skill-pill" data-category="design">UI/UX Design</span>
          <span class="skill-pill" data-category="frontend">Responsive CSS3</span>
          <span class="skill-pill" data-category="frontend">Bootstrap 5</span>
          <span class="skill-pill" data-category="frontend">Tailwind CSS</span>
          <span class="skill-pill" data-category="backend">REST APIs</span>
          <span class="skill-pill" data-category="devops">Git & CI/CD</span>
          <span class="skill-pill" data-category="devops">cPanel / Linux</span>
        </div>
      </div>
    </div>
  </section>

  <section class="process-section section-pad" aria-labelledby="process-title">
    <div class="shell section-heading reveal">
      <div><p class="eyebrow">A practical process</p><h2 id="process-title">Clear steps. Fewer surprises.</h2></div>
      <p>Each stage produces tangible deliverables, keeping architectural decisions visible and the project on schedule.</p>
    </div>
    <ol class="shell process-grid">
      <li class="reveal"><span>01</span><h3>Discover</h3><p>Define the target audience, user problems and measurable business outcomes through structured discussion.</p></li>
      <li class="reveal"><span>02</span><h3>Design</h3><p>Transform user journeys into clear wireframes, accessible interface layouts and interactive Figma prototypes.</p></li>
      <li class="reveal"><span>03</span><h3>Build</h3><p>Engineer the application using modern PHP 8+, PDO database design, semantic HTML and performant CSS/JavaScript.</p></li>
      <li class="reveal"><span>04</span><h3>Test</h3><p>Verify functionality, accessibility (WCAG 2.2 AA), responsive behavior, security controls and cross-browser reliability.</p></li>
      <li class="reveal"><span>05</span><h3>Launch</h3><p>Deploy carefully with structured hosting setup, caching, database indexing, and documentation for ongoing maintenance.</p></li>
    </ol>
  </section>

  <section class="stats-section section-pad surface-section" aria-labelledby="stats-title">
    <div class="shell">
      <div class="section-heading reveal" style="margin-bottom: 48px;">
        <div><p class="eyebrow">Core focus</p><h2 id="stats-title">Professional standards.</h2></div>
        <p>A proven commitment to dependable code, accessible interfaces and clean delivery.</p>
      </div>
      <div class="stats-grid reveal">
        <article class="stat-card">
          <div class="stat-value">Full-Stack</div>
          <div class="stat-label">End-to-end design & PHP development</div>
        </article>
        <article class="stat-card">
          <div class="stat-value">Standards</div>
          <div class="stat-label">WCAG 2.2 AA accessibility & SEO best practices</div>
        </article>
        <article class="stat-card">
          <div class="stat-value">Nairobi</div>
          <div class="stat-label">Based in Kenya · Serving clients globally</div>
        </article>
      </div>
    </div>
  </section>

  <section class="featured-work section-pad" aria-labelledby="work-title">
    <div class="shell section-heading reveal">
      <div><p class="eyebrow">Selected work</p><h2 id="work-title">Digital products shaped with <em>purpose.</em></h2></div>
      <p style="max-width: 720px; color: var(--text-dim);">A curated selection of verified design and full-stack development case studies. Visit the portfolio for all published work.</p>
    </div>
    <div class="shell project-grid" id="selected-work">
      <?php if (empty($featuredProjects)): ?>
        <div class="shell empty-state" style="grid-column: 1 / -1; text-align: center; padding: 48px 20px;">
          <p class="large-copy" style="color: var(--text); margin-bottom: 12px;">New case studies will be published soon.</p>
          <p style="color: var(--text-dim); max-width: 540px; margin-inline: auto; margin-bottom: 24px;">Project entries are managed directly through the administration dashboard. In the meantime, feel free to explore my capabilities or start a conversation.</p>
          <div class="button-row" style="justify-content: center; gap: 14px;">
            <a class="button" href="services.php">Explore services</a>
            <a class="button button-outline" href="contact.php">Start a project</a>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($featuredProjects as $project): ?>
          <?php
            $image = project_image_url($project['image_url'] ?? null);
            $detailUrl = 'portfolio-details.php?id=' . (int) $project['id'];
            $liveDemoUrl = safe_external_url($project['project_url'] ?? null);
            $githubUrl = safe_external_url($project['github_url'] ?? null);
            $techList = !empty($project['technologies']) ? array_filter(array_map('trim', explode(',', $project['technologies']))) : [];
          ?>
          <article class="project-card reveal">
            <a class="project-image" href="<?= e($detailUrl) ?>" aria-label="View case study for <?= e($project['title']) ?>">
              <img src="<?= e($image) ?>" width="900" height="620" loading="lazy" alt="<?= e($project['title']) ?> preview" onerror="this.onerror=null;this.src='<?= e(site_url('assets/img/cheroben_logo.webp')) ?>';">
              <span class="project-arrow"><i class="bi bi-arrow-up-right"></i></span>
            </a>
            <div class="project-meta">
              <span class="project-category"><?= e(ucwords(str_replace('-', ' ', (string) $project['category']))) ?></span>
              <h3><a href="<?= e($detailUrl) ?>"><?= e($project['title']) ?></a></h3>
              <p><?= e(text_excerpt((string) $project['description'], 160)) ?></p>
              <?php if (!empty($techList)): ?>
                <div class="project-tech-pills">
                  <?php foreach (array_slice($techList, 0, 4) as $tech): ?>
                    <span class="tech-chip"><?= e($tech) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <div class="project-actions">
                <a class="button button-small" href="<?= e($detailUrl) ?>">View Case Study <i class="bi bi-arrow-right"></i></a>
                <?php if ($liveDemoUrl): ?>
                  <a class="button button-small button-outline" href="<?= e($liveDemoUrl) ?>" target="_blank" rel="noopener noreferrer">Live Demo <i class="bi bi-box-arrow-up-right"></i></a>
                <?php endif; ?>
                <?php if ($githubUrl): ?>
                  <a class="button button-small button-outline" href="<?= e($githubUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="GitHub Repository for <?= e($project['title']) ?>"><i class="bi bi-github"></i> Code</a>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php if (!empty($featuredProjects)): ?>
      <div class="shell" style="text-align: center; margin-top: 48px;">
        <a class="button" href="portfolio.php">Browse all projects <i class="bi bi-arrow-right"></i></a>
      </div>
    <?php endif; ?>
  </section>

  <section class="about-preview section-pad">
    <div class="shell about-preview-grid reveal">
      <div class="portrait-frame">
        <img src="assets/img/profile-img.webp" width="900" height="1100" loading="lazy" alt="Portrait of Kennedy Cheroben">
      </div>
      <div>
        <p class="eyebrow">A little about me</p>
        <h2>Curious about the details. Focused on the whole experience.</h2>
        <p class="large-copy">I work across interface design and software engineering, which helps ideas survive the journey from an initial wireframe to a dependable, secure product.</p>
        <a class="text-link" href="about.php">More about my background and approach <i class="bi bi-arrow-right"></i></a>
      </div>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>