<?php
$pageTitle = 'Contact Kennedy Cheroben — Start a Project';
$pageDescription = 'Tell Kennedy Cheroben about your website, software or UI/UX project.';
$pagePath = 'contact.php';
$bodyClass = 'contact-page';
require __DIR__ . '/includes/head.php';
$activePage = 'contact';
require __DIR__ . '/includes/header.php';
$status = $_GET['status'] ?? '';
?>
<main id="main-content">
  <header class="page-hero shell"><p class="eyebrow">Contact</p><h1>Let’s talk about what you want to <em>build.</em></h1><p class="page-lead">Share the problem, the context and what a useful outcome would look like. I’ll reply by email.</p></header>
  <section class="section-pad"><div class="shell contact-grid"><aside><p class="section-index">Direct contact</p><a class="contact-detail" href="mailto:kennedycheroben001@gmail.com"><span>Email</span><strong>kennedycheroben001@gmail.com</strong></a><a class="contact-detail" href="tel:+254792399815"><span>Phone</span><strong>+254 792 399 815</strong></a><div class="contact-detail"><span>Based in</span><strong>Nairobi, Kenya</strong></div></aside><div class="form-panel">
      <?php if ($status === 'sent'): ?><div class="form-status success" role="status">Thanks—your message has been received.</div><?php elseif ($status === 'error'): ?><div class="form-status error" role="alert">The message could not be sent. Check the fields and try again.</div><?php endif; ?>
      <form action="forms/contact.php" method="post" data-contact-form>
        <?= csrf_field() ?><input type="hidden" name="started_at" value="<?= time() ?>"><div class="honeypot" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
        <div class="form-row"><label>What’s your name?<input name="name" type="text" maxlength="80" autocomplete="name" required></label><label>Your email address<input name="email" type="email" maxlength="160" autocomplete="email" required></label></div>
        <label>What can I help you with?<select name="subject" required><option value="">Choose a project type</option><option>Full-stack web application</option><option>Responsive website</option><option>UI/UX design</option><option>Maintenance or improvements</option><option>Something else</option></select></label>
        <label>Tell me a little about the project<textarea name="message" rows="7" minlength="20" maxlength="3000" required placeholder="The problem, audience, timeline and anything else that would help…"></textarea></label>
        <div class="form-submit"><p>By sending this form, you agree to the <a href="privacy.php">privacy policy</a>.</p><button class="button" type="submit">Send enquiry <i class="bi bi-arrow-up-right"></i></button></div>
      </form></div></div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
