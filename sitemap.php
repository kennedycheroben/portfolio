<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
define('DB_OPTIONAL', true);
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$projects = [];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT id, date, created_at FROM projects WHERE status = 'published' ORDER BY created_at DESC");
        $projects = $stmt ? $stmt->fetchAll() : [];
    } catch (PDOException $e) {
        error_log('Sitemap projects query error: ' . $e->getMessage());
    }
}

$today = date('Y-m-d');

$staticPages = [
    ['loc' => canonical_url(''), 'priority' => '1.0', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => canonical_url('portfolio.php'), 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => canonical_url('services.php'), 'priority' => '0.9', 'changefreq' => 'monthly', 'lastmod' => $today],
    ['loc' => canonical_url('about.php'), 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $today],
    ['loc' => canonical_url('service-details.php'), 'priority' => '0.7', 'changefreq' => 'monthly', 'lastmod' => $today],
    ['loc' => canonical_url('contact.php'), 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $today],
    ['loc' => canonical_url('privacy.php'), 'priority' => '0.3', 'changefreq' => 'yearly', 'lastmod' => '2026-08-18'],
    ['loc' => canonical_url('terms.php'), 'priority' => '0.3', 'changefreq' => 'yearly', 'lastmod' => '2026-08-18'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticPages as $page): ?>
  <url>
    <loc><?= e($page['loc']) ?></loc>
    <lastmod><?= e($page['lastmod']) ?></lastmod>
    <changefreq><?= e($page['changefreq']) ?></changefreq>
    <priority><?= e($page['priority']) ?></priority>
  </url>
<?php endforeach; ?>
<?php foreach ($projects as $project): ?>
  <?php
    $rawDate = $project['date'] ?: $project['created_at'] ?: $today;
    $lastmod = date('Y-m-d', strtotime((string) $rawDate));
  ?>
  <url>
    <loc><?= e(canonical_url('portfolio-details.php?id=' . (int) $project['id'])) ?></loc>
    <lastmod><?= e($lastmod) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
<?php endforeach; ?>
</urlset>
