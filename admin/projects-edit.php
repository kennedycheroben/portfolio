<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
  header('Location: projects.php');
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $title = input_string($_POST, 'title');
  $description = input_string($_POST, 'description');
  $category = input_string($_POST, 'category') ?: 'web-design';
  $project_url = input_string($_POST, 'project_url');
  $client = input_string($_POST, 'client');
  $date = input_string($_POST, 'date');
  $featured = isset($_POST['featured']) ? 1 : 0;
  $image_url = input_string($_POST, 'image_url');
  $video_url = input_string($_POST, 'video_url');

  if ($image_url === '') {
    $image_url = $project['image_url'];
  }

  $categories = ['web-design', 'software-development', 'mobile-app', 'branding', 'ui-ux', 'other'];
  if ($title === '' || text_length($title) > 140) {
    $error = 'Enter a title up to 140 characters.';
  } elseif (text_length($description) > 5000 || text_length($client) > 140 || !in_array($category, $categories, true)) {
    $error = 'Check the project details and category.';
  } elseif (!valid_iso_date($date)) {
    $error = 'Choose a valid project date.';
  } elseif (($image_url !== '' && $image_url !== $project['image_url'] && !safe_external_url($image_url)) || ($video_url !== '' && !safe_external_url($video_url)) || ($project_url !== '' && !safe_external_url($project_url))) {
    $error = 'URLs must use a valid HTTP or HTTPS address.';
  } else {
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
      try { $image_url = save_project_upload($_FILES['image']); } catch (RuntimeException $exception) { $error = $exception->getMessage(); }
    }

    if (!$error) {
      $stmt = $pdo->prepare('UPDATE projects SET title=?, description=?, category=?, image_url=?, video_url=?, project_url=?, client=?, date=?, featured=? WHERE id=?');
      $stmt->execute([$title, $description, $category, $image_url, $video_url, $project_url, $client, $date ?: null, $featured, $id]);
      redirect('projects.php?updated=1');
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Project - Portfolio</title>
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../assets/css/main.css?v=1.0.1" rel="stylesheet">
  <style>
    .admin-body { background: var(--background-color); min-height: 100vh; }
    .admin-sidebar { background: var(--surface-color); border-right: 1px solid color-mix(in srgb, var(--default-color), transparent 92%); width: 250px; position: fixed; top: 0; left: 0; height: 100vh; padding: 24px 16px; overflow-y: auto; }
    .admin-sidebar .brand { font-size: 20px; font-weight: 700; color: var(--accent-color); text-decoration: none; display: block; margin-bottom: 32px; }
    .admin-sidebar .nav-link { color: var(--default-color); padding: 10px 14px; border-radius: 8px; font-size: 14px; display: flex; align-items: center; gap: 10px; transition: 0.2s; text-decoration: none; }
    .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link.active { background: color-mix(in srgb, var(--accent-color), transparent 85%); color: var(--accent-color); }
    .admin-sidebar .nav-link i { font-size: 18px; }
    .admin-main { margin-left: 250px; padding: 32px; }
    .admin-main h1 { font-size: 28px; font-weight: 600; margin-bottom: 24px; }
    .form-card { background: var(--surface-color); border: 1px solid color-mix(in srgb, var(--default-color), transparent 92%); border-radius: 12px; padding: 32px; max-width: 720px; }
    .form-card .form-control, .form-card .form-select { background: color-mix(in srgb, var(--surface-color), white 5%); border: 1px solid color-mix(in srgb, var(--default-color), transparent 85%); color: var(--default-color); padding: 10px 14px; border-radius: 8px; }
    .form-card .form-control:focus, .form-card .form-select:focus { border-color: var(--accent-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent-color), transparent 80%); }
    .form-card .form-label { font-size: 14px; font-weight: 500; }
    .form-card textarea.form-control { min-height: 120px; }
    .form-card .btn-primary { background: var(--accent-color); border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; }
    .form-card .btn-primary:hover { background: color-mix(in srgb, var(--accent-color), black 15%); }
    .form-card .alert { border-radius: 8px; font-size: 14px; }
    .form-card .preview-img { max-width: 200px; border-radius: 8px; margin-top: 8px; }
    .form-check-input:checked { background-color: var(--accent-color); border-color: var(--accent-color); }
    .form-check-input:focus { box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent-color), transparent 80%); border-color: var(--accent-color); }

    /* Page entry animation */
    @keyframes fadeSlideUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .admin-main > * {
      animation: fadeSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
      opacity: 0;
    }
    .admin-main > *:nth-child(1) { animation-delay: 0.05s; }
    .admin-main > *:nth-child(2) { animation-delay: 0.1s; }
    .admin-main > *:nth-child(3) { animation-delay: 0.15s; }
    .admin-main > *:nth-child(4) { animation-delay: 0.2s; }
    .admin-main > *:nth-child(5) { animation-delay: 0.25s; }
    .admin-main > *:nth-child(6) { animation-delay: 0.3s; }

    /* Sidebar nav link active glow */
    .admin-sidebar .nav-link.active {
      position: relative;
      overflow: hidden;
    }
    .admin-sidebar .nav-link.active::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--accent-color), transparent 70%), transparent);
      animation: shimmer 2.5s ease-in-out infinite;
    }
    @keyframes shimmer {
      0% { transform: translateX(-100%); }
      100% { transform: translateX(100%); }
    }

    /* Mobile responsive */
    .sidebar-toggle {
      display: none;
      position: fixed;
      top: 12px;
      left: 12px;
      z-index: 1060;
      background: var(--surface-color);
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 85%);
      border-radius: 8px;
      padding: 8px 10px;
      cursor: pointer;
      font-size: 22px;
      color: var(--default-color);
      line-height: 1;
    }
    .sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1040;
      background: rgba(0,0,0,0.4);
    }
    .sidebar-overlay.show { display: block; }
    @media (max-width: 768px) {
      .sidebar-toggle { display: block; }
      .admin-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; z-index: 1050; }
      .admin-sidebar.open { transform: translateX(0); }
      .admin-main { margin-left: 0; padding: 70px 16px 24px; }
    }
  </style>
  <script>function toggleSidebar(){document.querySelector('.admin-sidebar').classList.toggle('open');document.querySelector('.sidebar-overlay').classList.toggle('show')}</script>
</head>
<body class="admin-body">
  <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
  <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
  <div class="admin-sidebar">
    <a href="index.php" class="brand"><i class="bi bi-code-slash"></i> Portfolio</a>
    <nav>
      <a href="index.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="projects.php" class="nav-link active"><i class="bi bi-folder"></i> Projects</a>
      <a href="messages.php" class="nav-link"><i class="bi bi-envelope"></i> Messages</a>
      <a href="../index.php" class="nav-link"><i class="bi bi-box-arrow-up-right"></i> View Site</a>
      <form action="../logout.php" method="post"><?= csrf_field() ?><button type="submit" class="nav-link border-0 bg-transparent w-100"><i class="bi bi-box-arrow-right"></i> Sign Out</button></form>
    </nav>
  </div>

  <div class="admin-main">
    <a href="projects.php" class="text-decoration-none mb-3 d-inline-block" style="color:var(--accent-color);"><i class="bi bi-arrow-left"></i> Back to Projects</a>
    <h1>Edit Project</h1>

    <div class="form-card">
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Title *</label>
          <input type="text" name="title" class="form-control" maxlength="140" value="<?= e($project['title']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" maxlength="5000"><?= e($project['description']) ?></textarea>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category" class="form-select">
              <?php foreach (['web-design' => 'Web Design', 'software-development' => 'Software Development', 'mobile-app' => 'Mobile App', 'branding' => 'Branding', 'ui-ux' => 'UI/UX Design', 'other' => 'Other'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= $project['category'] === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Project Date</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($project['date'] ?? '') ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Project URL</label>
          <input type="url" name="project_url" class="form-control" value="<?= htmlspecialchars($project['project_url']) ?>" placeholder="https://...">
        </div>
        <div class="mb-3">
          <label class="form-label">Client</label>
          <input type="text" name="client" class="form-control" maxlength="140" value="<?= e($project['client']) ?>" placeholder="Client or organization name">
        </div>
        <div class="mb-3">
          <label class="form-label">Project Image (File Upload)</label>
          <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
          <?php if ($project['image_url'] && !str_starts_with($project['image_url'], 'http')): ?>
            <img src="../<?= htmlspecialchars($project['image_url']) ?>" alt="" class="preview-img" loading="lazy">
          <?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label">Or Image Cloud URL (S3/R2)</label>
          <input type="url" name="image_url" class="form-control" value="<?= htmlspecialchars(str_starts_with($project['image_url'], 'http') ? $project['image_url'] : '') ?>" placeholder="https://bucket.r2.cloudflarestorage.com/image.png">
          <?php if ($project['image_url'] && str_starts_with($project['image_url'], 'http')): ?>
            <img src="<?= htmlspecialchars($project['image_url']) ?>" alt="" class="preview-img" loading="lazy">
          <?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label">Project Video Cloud URL (S3/R2 for Reels feed)</label>
          <input type="url" name="video_url" class="form-control" value="<?= htmlspecialchars($project['video_url'] ?? '') ?>" placeholder="https://bucket.r2.cloudflarestorage.com/video.mp4">
        </div>
        <div class="mb-4">
          <div class="form-check">
            <input type="checkbox" name="featured" id="featured" class="form-check-input" <?= $project['featured'] ? 'checked' : '' ?>>
            <label for="featured" class="form-check-label">Mark as featured</label>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Update Project</button>
      </form>
    </div>
  </div>
</body>
</html>
