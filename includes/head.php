<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pageTitle ??= 'Kennedy Cheroben — Visual Design Specialist & Full-Stack Developer';
$pageDescription ??= 'Visual design specialist and full-stack developer in Nairobi, Kenya. Crafting secure PHP applications, responsive websites and clear UI/UX designs.';
$pagePath ??= basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$bodyClass ??= '';
$ogType ??= 'website';
$ogImage ??= canonical_url('assets/img/hero-bg.webp');
$metaRobots ??= 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

if (!empty($canonicalUrl)) {
    $canonical = $canonicalUrl;
} elseif ($pagePath === 'index.php' || $pagePath === '' || $pagePath === '/') {
    $canonical = canonical_url('');
} else {
    $canonical = canonical_url($pagePath);
}

// Build structured data
$schemaGraph = [
    [
        '@type' => 'Person',
        '@id' => canonical_url('#person'),
        'name' => 'Kennedy Cheroben',
        'url' => canonical_url(''),
        'image' => canonical_url('assets/img/profile-img.webp'),
        'jobTitle' => 'Visual Design Specialist & Full-Stack Developer',
        'description' => 'Visual design specialist and full-stack developer in Nairobi, Kenya, building secure PHP applications, responsive modern websites and intuitive user interfaces.',
        'email' => 'mailto:kennedycheroben001@gmail.com',
        'telephone' => '+254792399815',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Nairobi',
            'addressCountry' => 'KE'
        ],
        'sameAs' => [
            'https://github.com/kennedycheroben'
        ],
        'knowsAbout' => [
            'PHP',
            'Laravel',
            'MySQL',
            'JavaScript',
            'HTML5',
            'CSS3',
            'UI/UX Design',
            'Figma',
            'REST APIs',
            'Responsive Web Design',
            'Web Application Security',
            'Database Architecture'
        ]
    ],
    [
        '@type' => 'WebSite',
        '@id' => canonical_url('#website'),
        'url' => canonical_url(''),
        'name' => 'Kennedy Cheroben: Visual Design Specialist & Full-Stack Developer',
        'description' => 'Portfolio of Kennedy Cheroben, visual design specialist and full-stack developer based in Nairobi, Kenya.',
        'inLanguage' => 'en-US',
        'publisher' => [
            '@id' => canonical_url('#person')
        ]
    ],
    [
        '@type' => 'ProfessionalService',
        '@id' => canonical_url('#service'),
        'name' => 'Kennedy Cheroben Web Development & UI/UX Design',
        'url' => canonical_url(''),
        'image' => canonical_url('assets/img/cheroben_logo.webp'),
        'telephone' => '+254792399815',
        'email' => 'kennedycheroben001@gmail.com',
        'priceRange' => '$$',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Nairobi',
            'addressCountry' => 'KE'
        ],
        'areaServed' => [
            ['@type' => 'Country', 'name' => 'Kenya'],
            ['@type' => 'AdministrativeArea', 'name' => 'Worldwide']
        ],
        'founder' => [
            '@id' => canonical_url('#person')
        ]
    ]
];

if (!empty($projectSchema) && is_array($projectSchema)) {
    $schemaGraph[] = $projectSchema;
}

if (!empty($extraSchema) && is_array($extraSchema)) {
    $schemaGraph = array_merge($schemaGraph, $extraSchema);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDescription) ?>">
  <meta name="robots" content="<?= e($metaRobots) ?>">
  <meta name="theme-color" content="#0a0a0c">
  <link rel="canonical" href="<?= e($canonical) ?>">

  <!-- Open Graph / Facebook -->
  <meta property="og:site_name" content="Kennedy Cheroben">
  <meta property="og:locale" content="en_KE">
  <meta property="og:type" content="<?= e($ogType) ?>">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($pageDescription) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">

  <!-- Twitter / X -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= e($pageDescription) ?>">
  <meta name="twitter:image" content="<?= e($ogImage) ?>">

  <!-- Icons -->
  <link rel="icon" type="image/x-icon" href="<?= e(site_url('favicon.ico')) ?>">
  <link rel="icon" type="image/webp" href="<?= e(site_url('assets/img/cheroben_logo.webp')) ?>">
  <link rel="apple-touch-icon" href="<?= e(site_url('assets/img/cheroben_logo.webp')) ?>">

  <script>document.documentElement.classList.add('js')</script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= e(site_url('assets/css/main.css?v=20261013_1')) ?>">

<?php if (!str_starts_with($pagePath, 'admin/')): ?>
  <link rel="preload" as="image" type="image/webp" href="<?= e(site_url('assets/img/hero-video-poster.webp')) ?>">
  <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@graph' => $schemaGraph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
</head>
<body id="top" class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
