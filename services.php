<?php
$pageTitle = 'Web Development & UI/UX Services — Kennedy Cheroben';
$pageDescription = 'Full-stack PHP development, responsive websites, UI/UX design, maintenance, performance and security improvements.';
$pagePath = 'services.php';
$bodyClass = 'services-page';
require __DIR__ . '/includes/head.php';
$activePage = 'services';
require __DIR__ . '/includes/header.php';
?>
<main id="main-content">
  <header class="page-hero shell"><p class="eyebrow">Services</p><h1>Useful digital work, from idea to <em>implementation.</em></h1><p class="page-lead">Focused design and development support for new products, existing websites and teams that need a thoughtful technical partner.</p></header>
  <section class="section-pad"><div class="shell service-card-grid">
    <article id="full-stack" class="service-card reveal"><span>01</span><i class="bi bi-braces"></i><h2>Full-stack PHP applications</h2><p>Custom web tools that support real business workflows with secure server-side logic and structured data.</p><ul><li>PHP 8+ application development</li><li>PDO and MySQL integration</li><li>Authentication and admin tools</li><li>Form and workflow implementation</li></ul><a class="text-link" href="service-details.php#full-stack">See the approach <i class="bi bi-arrow-right"></i></a></article>
    <article id="frontend" class="service-card reveal"><span>02</span><i class="bi bi-window"></i><h2>Responsive websites</h2><p>Clean, fast websites that communicate clearly and work comfortably across devices and input methods.</p><ul><li>Responsive frontend implementation</li><li>Accessible interaction patterns</li><li>Performance-conscious assets</li><li>Cross-browser refinement</li></ul><a class="text-link" href="service-details.php#frontend">See the approach <i class="bi bi-arrow-right"></i></a></article>
    <article id="design" class="service-card reveal"><span>03</span><i class="bi bi-bezier2"></i><h2>UI/UX design & prototyping</h2><p>Interfaces grounded in structure, clarity and the tasks your audience needs to complete.</p><ul><li>Information architecture</li><li>User flows and wireframes</li><li>Interactive prototypes</li><li>Reusable visual systems</li></ul><a class="text-link" href="service-details.php#design">See the approach <i class="bi bi-arrow-right"></i></a></article>
    <article id="care" class="service-card reveal"><span>04</span><i class="bi bi-speedometer2"></i><h2>Maintenance & improvements</h2><p>Focused work on existing products to make them safer, faster, clearer and easier to maintain.</p><ul><li>Security and code review</li><li>Performance improvements</li><li>Bug fixes and modernization</li><li>Ongoing product care</li></ul><a class="text-link" href="service-details.php#care">See the approach <i class="bi bi-arrow-right"></i></a></article>
  </div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
