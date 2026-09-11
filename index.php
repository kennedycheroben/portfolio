<?php
$pageTitle = 'Kennedy Cheroben — Full-Stack Developer & UI/UX Designer';
$pageDescription = 'I design and build secure, useful digital products from Nairobi, Kenya.';
$pagePath = 'index.php';
$bodyClass = 'home-page';
require __DIR__ . '/includes/head.php';
$activePage = 'home';
require __DIR__ . '/includes/header.php';
?>
<main id="main-content">
  <section class="sequence-hero" aria-labelledby="hero-title">
    <div class="sequence-sticky">
      <div class="shell hero-content">
        <p class="eyebrow">Full-Stack Developer <span>·</span> UI/UX Designer <span>·</span> Nairobi, Kenya</p>
        <h1 id="hero-title">I design and build digital products that work <em>beautifully.</em></h1>
        <p class="hero-lead">Product-minded design, secure PHP development and careful frontend craft—brought together to make useful experiences for real people.</p>
        <div class="button-row"><a class="button" href="#selected-work">View my work <i class="bi bi-arrow-down" aria-hidden="true"></i></a><a class="text-link" href="contact.php">Start a project <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
      </div>
      <div class="scroll-cue" aria-hidden="true"><span>Scroll to explore</span><i class="bi bi-arrow-down"></i></div>
    </div>
  </section>

  <section class="intro-section section-pad">
    <div class="shell intro-grid reveal">
      <p class="section-index">01 / Introduction</p>
      <div><h2>Design thinking.<br>Engineering discipline.</h2><p class="large-copy">I’m Kennedy, a developer and designer focused on turning complex ideas into clear, responsive and maintainable digital products.</p></div>
    </div>
    <div class="shell tech-marquee" aria-label="Core technologies" tabindex="0">
      <div class="tech-marquee-track">
        <div class="tech-marquee-group">
          <span class="tech-pill">PHP</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">MySQL</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">JavaScript</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">HTML5</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">CSS3</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">Bootstrap</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">UI/UX Design</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">REST APIs</span><span class="tech-dot" aria-hidden="true">·</span>
        </div>
        <div class="tech-marquee-group" aria-hidden="true">
          <span class="tech-pill">PHP</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">MySQL</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">JavaScript</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">HTML5</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">CSS3</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">Bootstrap</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">UI/UX Design</span><span class="tech-dot" aria-hidden="true">·</span>
          <span class="tech-pill">REST APIs</span><span class="tech-dot" aria-hidden="true">·</span>
        </div>
      </div>
    </div>
  </section>

  <section class="process-section section-pad" aria-labelledby="process-title">
    <div class="shell section-heading reveal"><div><p class="eyebrow">A practical process</p><h2 id="process-title">Clear steps. Fewer surprises.</h2></div><p>Each stage produces something useful, so decisions stay visible and the project keeps moving.</p></div>
    <ol class="shell process-grid"><li class="reveal"><span>01</span><h3>Discover</h3><p>Define the audience, problem and the shape of a useful outcome.</p></li><li class="reveal"><span>02</span><h3>Design</h3><p>Turn requirements into flows, structure and an interface direction.</p></li><li class="reveal"><span>03</span><h3>Build</h3><p>Develop the product with clean, responsive and maintainable code.</p></li><li class="reveal"><span>04</span><h3>Test</h3><p>Check behavior, accessibility, performance and security.</p></li><li class="reveal"><span>05</span><h3>Launch</h3><p>Prepare a controlled release and a clear path for ongoing care.</p></li></ol>
  </section>

  <section class="about-preview section-pad">
    <div class="shell about-preview-grid reveal"><div class="portrait-frame"><img src="assets/img/profile-img.webp" width="900" height="1100" loading="lazy" alt="Portrait of Kennedy Cheroben"></div><div><p class="eyebrow">A little about me</p><h2>Curious about the details. Focused on the whole experience.</h2><p class="large-copy">I work across interface design and development, which helps ideas survive the journey from a screen mockup to a dependable product.</p><a class="text-link" href="about.php">More about my approach <i class="bi bi-arrow-right"></i></a></div></div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
