<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';

if (!$pdo instanceof PDO) {
    http_response_code(503);
    exit('Database connection unavailable.');
}

$error = '';
$title = '';
$description = '';
$problem = '';
$contribution = '';
$features = '';
$outcomes = '';
$category = 'web-design';
$technologies = '';
$project_url = '';
$github_url = '';
$client = '';
$date = date('Y-m-d');
$featured = 0;
$status = 'published';
$image_url = '';
$video_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = input_string($_POST, 'title');
    $description = input_string($_POST, 'description');
    $problem = input_string($_POST, 'problem');
    $contribution = input_string($_POST, 'contribution');
    $features = input_string($_POST, 'features');
    $outcomes = input_string($_POST, 'outcomes');
    $category = input_string($_POST, 'category') ?: 'web-design';
    $technologies = input_string($_POST, 'technologies');
    $project_url = input_string($_POST, 'project_url');
    $github_url = input_string($_POST, 'github_url');
    $client = input_string($_POST, 'client');
    $date = input_string($_POST, 'date');
    $featured = isset($_POST['featured']) ? 1 : 0;
    $status = input_string($_POST, 'status') === 'draft' ? 'draft' : 'published';
    $image_url = input_string($_POST, 'image_url');
    $video_url = input_string($_POST, 'video_url');

    $categories = ['web-design', 'software-development', 'mobile-app', 'branding', 'ui-ux', 'other'];

    if ($title === '' || text_length($title) > 140) {
        $error = 'Enter a title up to 140 characters.';
    } elseif (text_length($description) > 5000 || text_length($problem) > 5000 || text_length($contribution) > 5000 || text_length($features) > 5000 || text_length($outcomes) > 5000) {
        $error = 'One or more text sections exceed the 5000 character limit.';
    } elseif (text_length($client) > 140 || text_length($technologies) > 500) {
        $error = 'Client or technologies exceed length limits.';
    } elseif (!in_array($category, $categories, true)) {
        $error = 'Choose a valid category.';
    } elseif (!valid_iso_date($date)) {
        $error = 'Choose a valid project date.';
    } else {
        if (($image_url !== '' && !safe_external_url($image_url)) || ($video_url !== '' && !safe_external_url($video_url)) || ($project_url !== '' && !safe_external_url($project_url)) || ($github_url !== '' && !safe_external_url($github_url))) {
            $error = 'All URLs must be valid HTTP or HTTPS addresses.';
        }

        if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $image_url = save_project_upload($_FILES['image']);
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare('INSERT INTO projects (
                title, description, problem, contribution, features, outcomes,
                category, technologies, image_url, video_url, project_url, github_url,
                client, date, featured, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

            $stmt->execute([
                $title,
                $description !== '' ? $description : null,
                $problem !== '' ? $problem : null,
                $contribution !== '' ? $contribution : null,
                $features !== '' ? $features : null,
                $outcomes !== '' ? $outcomes : null,
                $category,
                $technologies !== '' ? $technologies : null,
                $image_url !== '' ? $image_url : null,
                $video_url !== '' ? $video_url : null,
                $project_url !== '' ? $project_url : null,
                $github_url !== '' ? $github_url : null,
                $client !== '' ? $client : null,
                $date ?: null,
                $featured,
                $status
            ]);
            redirect('projects.php?added=1');
        }
    }
}

$adminPageTitle = 'Add Project';
$adminActiveNav = 'projects';
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
      <a href="projects.php" class="text-decoration-none mb-1 d-inline-block" style="color: var(--admin-accent); font-size: 0.875rem;"><i class="bi bi-arrow-left"></i> Back to projects</a>
      <h1 class="mb-0">Add New Project</h1>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i> <?= e($error) ?></div>
  <?php endif; ?>

  <div class="form-card">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="row g-3 mb-4">
        <div class="col-md-8">
          <label class="form-label" for="projectTitle">Project Title <span style="color:#ef4444;">*</span></label>
          <input type="text" id="projectTitle" name="title" class="form-control" value="<?= e($title) ?>" maxlength="140" required placeholder="e.g. MediCare HMS">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="projectStatus">Publishing Status</label>
          <select id="projectStatus" name="status" class="form-select">
            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published (Live)</option>
            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft (Private)</option>
          </select>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label" for="projectCategory">Category <span style="color:#ef4444;">*</span></label>
          <select id="projectCategory" name="category" class="form-select" required>
            <option value="web-design" <?= $category === 'web-design' ? 'selected' : '' ?>>Web Design</option>
            <option value="software-development" <?= $category === 'software-development' ? 'selected' : '' ?>>Software Development</option>
            <option value="mobile-app" <?= $category === 'mobile-app' ? 'selected' : '' ?>>Mobile App</option>
            <option value="ui-ux" <?= $category === 'ui-ux' ? 'selected' : '' ?>>UI / UX Design</option>
            <option value="branding" <?= $category === 'branding' ? 'selected' : '' ?>>Branding</option>
            <option value="other" <?= $category === 'other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="projectDate">Project Date</label>
          <input type="date" id="projectDate" name="date" class="form-control" value="<?= e($date) ?>">
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label" for="projectClient">Client / Organization</label>
          <input type="text" id="projectClient" name="client" class="form-control" value="<?= e($client) ?>" maxlength="140" placeholder="e.g. HealthCare Group / Internal">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="projectTech">Technologies (comma-separated)</label>
          <input type="text" id="projectTech" name="technologies" class="form-control" value="<?= e($technologies) ?>" maxlength="500" placeholder="e.g. PHP 8, MySQL, JavaScript, Figma, Tailwind CSS">
          <div class="form-text">Will be displayed as technology pills on the project cards and case study.</div>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label" for="projectLiveUrl">Live Demo URL</label>
          <input type="url" id="projectLiveUrl" name="project_url" class="form-control" value="<?= e($project_url) ?>" placeholder="https://example.com">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="projectGithubUrl">GitHub Repository URL</label>
          <input type="url" id="projectGithubUrl" name="github_url" class="form-control" value="<?= e($github_url) ?>" placeholder="https://github.com/kennedycheroben/...">
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label" for="projectImage">Upload Image (Max 5MB)</label>
          <input type="file" id="projectImage" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
          <div class="form-text">Supported formats: JPEG, PNG, WebP.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="projectImageUrl">Or Image URL</label>
          <input type="text" id="projectImageUrl" name="image_url" class="form-control" value="<?= e($image_url) ?>" placeholder="assets/img/... or https://...">
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label" for="projectVideoUrl">Video URL (optional)</label>
        <input type="url" id="projectVideoUrl" name="video_url" class="form-control" value="<?= e($video_url) ?>" placeholder="https://assets.example.com/video.mp4">
      </div>

      <div class="mb-4">
        <div class="form-check">
          <input type="checkbox" class="form-check-input" id="projectFeatured" name="featured" value="1" <?= $featured ? 'checked' : '' ?>>
          <label class="form-check-label" for="projectFeatured">Feature this project on the homepage</label>
        </div>
      </div>

      <hr style="border-color: var(--admin-border); margin: 32px 0;">
      <h2 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 20px; color: var(--admin-text);">Case Study Narrative Details</h2>

      <div class="mb-4">
        <label class="form-label" for="projectDesc">Project Overview / Summary</label>
        <textarea id="projectDesc" name="description" class="form-control" rows="4" maxlength="5000" placeholder="Brief synopsis of what the project is and what it accomplishes..."><?= e($description) ?></textarea>
      </div>

      <div class="mb-4">
        <label class="form-label" for="projectProblem">The Challenge / Problem Statement</label>
        <textarea id="projectProblem" name="problem" class="form-control" rows="4" maxlength="5000" placeholder="What core problem or operational challenge was this project built to solve?"><?= e($problem) ?></textarea>
      </div>

      <div class="mb-4">
        <label class="form-label" for="projectContribution">Contribution & My Role</label>
        <textarea id="projectContribution" name="contribution" class="form-control" rows="4" maxlength="5000" placeholder="Your specific role: architecture, database design, frontend implementation, UI/UX prototyping in Figma, etc..."><?= e($contribution) ?></textarea>
      </div>

      <div class="mb-4">
        <label class="form-label" for="projectFeatures">Key Features & Architecture</label>
        <textarea id="projectFeatures" name="features" class="form-control" rows="4" maxlength="5000" placeholder="Describe the main functional modules, user roles, security protections, APIs, etc..."><?= e($features) ?></textarea>
      </div>

      <div class="mb-4">
        <label class="form-label" for="projectOutcomes">Outcomes & Results</label>
        <textarea id="projectOutcomes" name="outcomes" class="form-control" rows="3" maxlength="5000" placeholder="Verified results, key takeaways, deployment metrics, user feedback..."><?= e($outcomes) ?></textarea>
      </div>

      <div class="d-flex gap-3 pt-3">
        <button type="submit" class="btn-accent"><i class="bi bi-save" aria-hidden="true"></i> Save Project</button>
        <a href="projects.php" class="btn-admin-view">Cancel</a>
      </div>
    </form>
  </div>
</main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
