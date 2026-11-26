<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/devpilot-loader.php';
devpilot_register_autoloader();

use DevPilot\Website\WebsiteAuth;
use DevPilot\Website\WebsiteServices;

if (!$pdo instanceof PDO) { http_response_code(503); exit('Hub database unavailable.'); }
$ownerId = WebsiteAuth::currentAdminId();
$projects = WebsiteServices::connectedProjects($pdo)->listOwned($ownerId);
$pendingRequests = WebsiteServices::hubPairings($pdo)->listPending();
$adminPageTitle = 'Connected DevPilot Projects';
$adminActiveNav = 'devpilot-websites';
$unreadCount = 0;
$connectorEnabled = in_array((string) getenv('APP_ENV'), ['development','local','staging'], true)
    || (getenv('APP_ENV') === 'production' && getenv('DEVPILOT_CONNECTOR_SECURITY_VERIFIED') === '1');
require __DIR__ . '/includes/admin-head.php';
require __DIR__ . '/includes/admin-sidebar.php';
?>
<main class="admin-main" id="main-content"><div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3"><div><h1 class="mb-1">Connected customer projects</h1><p class="text-muted mb-0">Connections are scoped to your Hub administrator account.</p></div><a class="btn-secondary" href="devpilot.php">Back to DevPilot</a></div>
<?php if (!$connectorEnabled): ?><div class="alert alert-info">Hub inspection refresh is disabled until production security review is complete. Revocation remains available.</div><?php endif; ?>
<?php if ($pendingRequests !== []): ?><section class="card mb-4"><div class="card-header"><strong>Pending project approvals</strong></div><div class="list-group list-group-flush"><?php foreach ($pendingRequests as $pending): ?><a class="list-group-item list-group-item-action" href="devpilot-connector-approve.php?request=<?= e((string)$pending['request_id']) ?>"><strong><?= e((string)$pending['project_name']) ?></strong><br><small><?= e((string)$pending['origin']) ?> · <?= e(implode(', ',(array)$pending['scopes'])) ?></small></a><?php endforeach; ?></div></section><?php endif; ?>
<div id="connector-feedback" class="visually-hidden" role="status" aria-live="polite"></div>
<section class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Project</th><th>Connection</th><th>Permissions</th><th>Latest inspection</th><th>Actions</th></tr></thead><tbody>
<?php if ($projects === []): ?><tr><td colspan="5" class="text-center p-4">No customer projects are connected.</td></tr><?php endif; ?>
<?php foreach ($projects as $project): $inspection = is_array($project['inspection'] ?? null) ? $project['inspection'] : []; ?>
<tr><td><strong><?= e((string) $project['project_name']) ?></strong><br><small><?= e((string) $project['project_id']) ?><br><?= e((string) $project['origin']) ?></small></td><td><?= e((string) $project['status']) ?><br><small>Connected <?= is_numeric($project['connected_at'] ?? null) ? e(date('Y-m-d H:i:s T',(int)$project['connected_at'])) : '—' ?></small></td><td><?= e(implode(', ',(array)$project['scopes'])) ?></td><td><?php if ($inspection !== []): ?><?= e((string)($inspection['connection_health'] ?? 'unknown')) ?> · <?= e((string)($inspection['framework'] ?? 'unknown')) ?> · PHP <?= e((string)($inspection['runtime'] ?? '')) ?><br><small>Inspected <?= is_numeric($project['last_inspected_at'] ?? null) ? e(date('Y-m-d H:i:s T',(int)$project['last_inspected_at'])) : '—' ?></small><?php else: ?>Not inspected<?php endif; ?><?php if (!empty($project['last_error'])): ?><br><span class="text-danger"><?= e((string)$project['last_error']) ?></span><?php endif; ?></td><td><?php if ($project['status'] === 'active'): ?><div class="d-flex gap-2 flex-wrap"><form class="project-action" method="post" action="api/devpilot-connected-projects.php"><?= csrf_field() ?><input type="hidden" name="action" value="refresh"><input type="hidden" name="project_id" value="<?= e((string)$project['project_id']) ?>"><button class="btn-secondary" type="submit" <?= $connectorEnabled ? '' : 'disabled' ?>>Refresh</button></form><form class="project-action" method="post" action="api/devpilot-connected-projects.php"><?= csrf_field() ?><input type="hidden" name="action" value="disconnect"><input type="hidden" name="project_id" value="<?= e((string)$project['project_id']) ?>"><button class="btn-secondary" type="submit">Disconnect</button></form></div><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></section>
<script>
document.querySelectorAll('.project-action').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('button');const feedback=document.getElementById('connector-feedback');button.disabled=true;try{const response=await fetch(form.action,{method:'POST',headers:{'X-CSRF-Token':form.querySelector('[name="csrf_token"]').value},body:new FormData(form)});const data=await response.json();if(!response.ok||!data.success)throw new Error(data.error||'Project action failed.');feedback.textContent=data.message;location.reload();}catch(error){alert(error.message);button.disabled=false;}}));
</script>
</main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
