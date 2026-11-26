<?php
$pageTitle = 'Service Approach — Kennedy Cheroben';
$pageDescription = 'A practical look at Kennedy Cheroben\'s full-stack development, frontend, UI/UX and product improvement services.';
$pagePath = 'service-details.php';
$bodyClass = 'service-details-page';
require __DIR__ . '/includes/head.php';
$activePage = 'services';
require __DIR__ . '/includes/header.php';
?>
<main id="main-content">
  <header class="page-hero shell">
    <p class="eyebrow">How I can help</p>
    <h1>Thoughtful work needs both a good idea and a <em>solid finish.</em></h1>
  </header>
  <section class="section-pad">
    <div class="shell detail-list">
      <article id="full-stack">
        <span>01</span>
        <div>
          <h2>Full-stack PHP applications</h2>
          <p class="large-copy">From data model to interface, I build connected tools that are clear to use and practical to maintain.</p>
          <p>Typical work includes PHP 8+ application logic, PDO-backed MySQL integration, form workflows, authentication, administrator interfaces and responsive frontend implementation.</p>
        </div>
      </article>
      <article id="frontend">
        <span>02</span>
        <div>
          <h2>Responsive websites</h2>
          <p class="large-copy">A strong website should communicate quickly and behave predictably everywhere.</p>
          <p>I translate visual direction into semantic, accessible interfaces with responsive layouts, purposeful interaction and performance-conscious assets.</p>
        </div>
      </article>
      <article id="design">
        <span>03</span>
        <div>
          <h2>UI/UX design & prototyping</h2>
          <p class="large-copy">Good interface design gives people confidence about what to do next.</p>
          <p>I organize content, map flows, explore layouts and create prototypes that make decisions tangible before development becomes expensive.</p>
        </div>
      </article>
      <article id="care">
        <span>04</span>
        <div>
          <h2>Maintenance, performance & security</h2>
          <p class="large-copy">Existing products can often become significantly better without starting again.</p>
          <p>I review weak points, prioritize changes and improve code quality, loading behavior, validation, accessibility and day-to-day reliability.</p>
        </div>
      </article>
    </div>
  </section>
  <section class="stats-section section-pad surface-section">
    <div class="shell">
      <div class="section-heading reveal" style="margin-bottom: 48px;">
        <div><p class="eyebrow">Engagement models</p><h2>Flexible ways to work together.</h2></div>
      </div>
      <div class="stats-grid reveal">
        <article class="stat-card">
          <div class="stat-value">Project</div>
          <div class="stat-label">Fixed scope, fixed timeline</div>
        </article>
        <article class="stat-card">
          <div class="stat-value">Retainer</div>
          <div class="stat-label">Ongoing partnership</div>
        </article>
        <article class="stat-card">
          <div class="stat-value">Consulting</div>
          <div class="stat-label">Strategy & code review</div>
        </article>
        <article class="stat-card">
          <div class="stat-value">Rescue</div>
          <div class="stat-label">Fix what's broken</div>
        </article>
      </div>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>