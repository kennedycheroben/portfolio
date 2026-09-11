<?php
$pageTitle = 'About Kennedy Cheroben — Developer & Designer';
$pageDescription = 'Learn about Kennedy Cheroben, a full-stack developer and UI/UX designer based in Nairobi, Kenya.';
$pagePath = 'about.php';
$bodyClass = 'about-page';
require __DIR__ . '/includes/head.php';
$activePage = 'about';
require __DIR__ . '/includes/header.php';
?>
<main id="main-content">
  <header class="page-hero shell"><p class="eyebrow">About me</p><h1>I care about how digital products <em>feel</em> and how they <em>hold up.</em></h1></header>
  <section class="section-pad"><div class="shell about-story-grid"><div class="portrait-frame portrait-tall reveal"><img src="assets/img/profile-img.webp" width="900" height="1100" alt="Kennedy Cheroben" fetchpriority="high"></div><div class="story-copy reveal"><p class="large-copy">I’m Kennedy Cheroben, a full-stack developer and UI/UX designer based in Nairobi, Kenya.</p><p>My work sits where thoughtful interfaces meet dependable engineering. I enjoy understanding the problem behind a brief, simplifying what feels complicated, and then building an experience that is clean, responsive and useful.</p><p>Working across design and development gives me a practical view of the whole product—from information structure and interaction details to PHP, databases, performance and security.</p><a class="button" href="contact.php">Work with me <i class="bi bi-arrow-up-right"></i></a></div></div></section>
  <section class="section-pad surface-section"><div class="shell split-heading"><p class="section-index">Capabilities</p><h2>What I bring to a project.</h2></div><div class="shell capability-grid"><article><i class="bi bi-bezier2"></i><h3>Product & interface design</h3><p>User flows, wireframes, prototypes and clear visual systems.</p></article><article><i class="bi bi-code-slash"></i><h3>Frontend craft</h3><p>Responsive, accessible HTML, CSS and JavaScript interfaces.</p></article><article><i class="bi bi-braces"></i><h3>Backend development</h3><p>PHP and MySQL applications with maintainable server-side logic.</p></article><article><i class="bi bi-shield-check"></i><h3>Quality & resilience</h3><p>Attention to performance, security, validation and dependable behavior.</p></article></div></section>
  <section class="section-pad"><div class="shell values-grid"><div><p class="eyebrow">How I work</p><h2>Good work is clear work.</h2></div><div class="value-list"><article><span>01</span><div><h3>Start with the real problem</h3><p>Features matter only when they solve something meaningful for the people using them.</p></div></article><article><span>02</span><div><h3>Make decisions visible</h3><p>Clear rationale and regular checkpoints keep projects aligned.</p></div></article><article><span>03</span><div><h3>Sweat the useful details</h3><p>Accessibility, loading behavior, validation and edge cases are part of the experience.</p></div></article></div></div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
