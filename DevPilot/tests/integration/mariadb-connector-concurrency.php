<?php
declare(strict_types=1);

use DevPilot\Connector\ConnectorPolicy;
use DevPilot\Connector\PdoConnectorStore;
use DevPilot\Connector\ProjectConnectorService;

require_once dirname(__DIR__, 2) . '/autoload.php';

function mariaAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function mariaPdo(string $dsn): PDO
{
    $pdo = new PDO($dsn, (string) getenv('DEVPILOT_MARIADB_TEST_USER'), (string) getenv('DEVPILOT_MARIADB_TEST_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL " . ((getenv('DEVPILOT_MARIADB_TEST_ISOLATION') === 'READ-COMMITTED') ? 'READ COMMITTED' : 'REPEATABLE READ'));
    return $pdo;
}

function launchMariaWorker(string $dsn, string $action, string $payload, int $owner, string $barrier, string $directory, int $index): array
{
    $output = $directory . '/worker-' . $index . '.out';
    $error = $directory . '/worker-' . $index . '.err';
    $command = [PHP_BINARY, __FILE__, 'worker', $dsn, $action, $payload, (string) $owner, $barrier, (string) time()];
    $process = proc_open($command, [0=>['pipe','r'],1=>['file',$output,'wb'],2=>['file',$error,'wb']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start MariaDB concurrency worker.');
    fclose($pipes[0]);
    return ['process'=>$process,'output'=>$output,'error'=>$error];
}

if (($argv[1] ?? '') === 'worker') {
    try {
        [,,$dsn,$action,$payload,$owner,$barrier,$now] = $argv;
        $pdo = mariaPdo($dsn);
        $service = new ProjectConnectorService(new PdoConnectorStore($pdo));
        $deadline = microtime(true) + 20;
        while (!is_file($barrier) && microtime(true) < $deadline) usleep(1000);
        if (!is_file($barrier)) throw new RuntimeException('Concurrency barrier timed out.');
        $result = $action === 'rotate'
            ? $service->rotate($payload,(int) $now)
            : $service->authorize($payload,'shared-mariadb-project','Shared MariaDB Project','https://project.example.org',[ConnectorPolicy::INSPECT],(int) $now);
        echo json_encode($result, JSON_THROW_ON_ERROR);
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage());
        exit(1);
    }
}

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devpilot-mariadb-concurrency-' . bin2hex(random_bytes(6));
$adminPdo = null;
$projectPdo = null;
$hubPdo = null;
$databaseNames = [];
$workers = [];
$stage = 'setup';
$cleanupErrors = [];
$successMessage = null;
try {
    if (getenv('DEVPILOT_MARIADB_TEST_ALLOW') !== '1') throw new RuntimeException('Set DEVPILOT_MARIADB_TEST_ALLOW=1 to enable the isolated MariaDB integration test.');
    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) throw new RuntimeException('PDO MySQL is unavailable in this PHP CLI.');
    $host = (string) (getenv('DEVPILOT_MARIADB_TEST_HOST') ?: '127.0.0.1');
    if (!in_array(strtolower($host), ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('MariaDB test host must be local; remote database hosts are refused.');
    $port = (int) (getenv('DEVPILOT_MARIADB_TEST_PORT') ?: 3306);
    if ($port < 1 || $port > 65535) throw new RuntimeException('MariaDB test port is invalid.');
    $adminDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $adminPdo = mariaPdo($adminDsn);
    $baseName = 'devpilot_test_connector_' . bin2hex(random_bytes(6));
    $projectDbName = $baseName . '_project';
    $hubDbName = $baseName . '_hub';
    foreach ([$projectDbName,$hubDbName] as $databaseName) {
        $adminPdo->exec('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $databaseNames[] = $databaseName;
    }
    if (!mkdir($temporaryDirectory,0700,true)) throw new RuntimeException('Could not create MariaDB test workspace.');
    $projectDsn = $adminDsn . ';dbname=' . $projectDbName;
    $hubDsn = $adminDsn . ';dbname=' . $hubDbName;
    $projectPdo = mariaPdo($projectDsn);
    $hubPdo = mariaPdo($hubDsn);
    $root = dirname(__DIR__, 3);
    $connectorMigration = file_get_contents($root . '/migrations/004_devpilot_connector.sql');
    if (!is_string($connectorMigration)) throw new RuntimeException('Could not read migration 004.');
    $connectorMigration = preg_replace('/^[ \t]*--[^\r\n]*\R?/m', '', $connectorMigration) ?? $connectorMigration;
    foreach (array_filter(array_map('trim', explode(';', $connectorMigration))) as $statement) $projectPdo->exec($statement);
    $legacyPairing = $projectPdo->prepare('INSERT INTO devpilot_connector_pairings (code_hash,project_id,project_name,origin,scopes_json,expires_at) VALUES (?,\'legacy-project\',\'Legacy\',\'https://legacy.example.org\',?,DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 5 MINUTE))');
    $legacyPairing->execute([random_bytes(32),json_encode([ConnectorPolicy::INSPECT],JSON_THROW_ON_ERROR)]);
    $legacyConnection = $projectPdo->prepare('INSERT INTO devpilot_connector_connections (connection_id,project_id,project_name,origin,scopes_json,token_hash,status,connected_at,last_seen_at,expires_at) VALUES (\'legacy-connection\',\'legacy-project\',\'Legacy\',\'https://legacy.example.org\',?, ?,\'active\',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 30 DAY))');
    $legacyConnection->execute([json_encode([ConnectorPolicy::INSPECT],JSON_THROW_ON_ERROR),random_bytes(32)]);
    $ownerMigration = file_get_contents($root . '/migrations/005_devpilot_project_owner.sql');
    if (!is_string($ownerMigration)) throw new RuntimeException('Could not read migration 005.');
    $ownerMigration = preg_replace('/^[ \t]*--[^\r\n]*\R?/m', '', $ownerMigration) ?? $ownerMigration;
    foreach (array_filter(array_map('trim', explode(';', $ownerMigration))) as $statement) $projectPdo->exec($statement);
    mariaAssert($projectPdo->query("SELECT status FROM devpilot_connector_connections WHERE connection_id='legacy-connection'")->fetchColumn() === 'revoked','Migration 005 did not revoke pre-owner credentials.');
    mariaAssert($projectPdo->query("SELECT used_at IS NOT NULL FROM devpilot_connector_pairings WHERE project_id='legacy-project'")->fetchColumn() == 1,'Migration 005 did not invalidate pre-owner pairing codes.');
    $hubMigration = file_get_contents($root . '/migrations/006_devpilot_hub_registry.sql');
    if (!is_string($hubMigration)) throw new RuntimeException('Could not read Hub migration 006.');
    $hubMigration = preg_replace('/^[ \t]*--[^\r\n]*\R?/m', '', $hubMigration) ?? $hubMigration;
    foreach (array_filter(array_map('trim', explode(';', $hubMigration))) as $statement) $hubPdo->exec($statement);

    $service = new ProjectConnectorService(new PdoConnectorStore($projectPdo));
    $makePairing = static function (int $owner) use ($service): string {
        return $service->beginPairing('shared-mariadb-project','Shared MariaDB Project','https://project.example.org',[ConnectorPolicy::INSPECT],time(),$owner)['pairing_code'];
    };
    $runBatch = static function (array $jobs, bool $tolerateFailures = false) use (&$workers,$projectDsn,$temporaryDirectory): array {
        $barrier = $temporaryDirectory . '/release-' . bin2hex(random_bytes(4));
        $batch = [];
        foreach ($jobs as $index=>$job) {
            $worker = launchMariaWorker($projectDsn,$job['action'] ?? 'authorize',$job['payload'],$job['owner'],$barrier,$temporaryDirectory,$index + count($workers));
            $workers[] = $worker;
            $batch[] = $worker;
        }
        file_put_contents($barrier,'go');
        $results = [];
        foreach ($batch as $worker) {
            $exit = proc_close($worker['process']);
            $stdout = file_get_contents($worker['output']);
            $stderr = file_get_contents($worker['error']);
            if ($exit !== 0) {
                if (!$tolerateFailures) throw new RuntimeException('MariaDB worker failed: ' . (string) $stderr);
                $results[] = ['ok'=>false,'error'=>trim((string) $stderr)];
                continue;
            }
            $result = json_decode((string) $stdout,true);
            if (!is_array($result) || !isset($result['access_token'])) {
                if (!$tolerateFailures) throw new RuntimeException('MariaDB worker returned invalid connection data.');
                $results[] = ['ok'=>false,'error'=>'invalid worker output: ' . (string) $stdout];
                continue;
            }
            $result['ok'] = true;
            $results[] = $result;
        }
        return $results;
    };

    $stage = 'concurrent initial connections for separate owners';
    $first = $runBatch([['owner'=>101,'payload'=>$makePairing(101)],['owner'=>202,'payload'=>$makePairing(202)]]);
    mariaAssert($service->status('shared-mariadb-project',101)['connection_id'] !== $service->status('shared-mariadb-project',202)['connection_id'],'Different owners did not receive isolated records.');

    $oldOwnerATokens = [$first[0]['access_token']];
    $stage = 'concurrent reconnects for the same owner';
    $reconnected = $runBatch([['owner'=>101,'payload'=>$makePairing(101)],['owner'=>101,'payload'=>$makePairing(101)]]);
    $activeRows = $projectPdo->query("SELECT owner_id, COUNT(*) AS active_count FROM devpilot_connector_connections WHERE project_id='shared-mariadb-project' AND status='active' GROUP BY owner_id")->fetchAll();
    $activeCounts = [];
    foreach ($activeRows as $row) $activeCounts[(int) $row['owner_id']] = (int) $row['active_count'];
    mariaAssert(($activeCounts[101] ?? 0) === 1,'Concurrent reconnect created more than one active connection for the same owner.');
    mariaAssert(($activeCounts[202] ?? 0) === 1,'Owner A reconnect changed Owner B active state.');
    foreach ($oldOwnerATokens as $token) {
        try { $service->inspect($token,time()); throw new RuntimeException('A pre-reconnect token remained valid.'); }
        catch (RuntimeException $exception) { if ($exception->getMessage() === 'A pre-reconnect token remained valid.') throw $exception; }
    }
    $validReconnectedTokens = 0;
    foreach ($reconnected as $connection) {
        try { $service->inspect($connection['access_token'],time()); $validReconnectedTokens++; }
        catch (RuntimeException) { }
    }
    mariaAssert($validReconnectedTokens === 1,'Concurrent reconnects did not leave exactly one returned token active.');
    $ownerBToken = $first[1]['access_token'];
    $currentOwnerA = $service->status('shared-mariadb-project',101);
    $currentOwnerAToken = null;
    foreach ($reconnected as $connection) if ($connection['connection_id'] === $currentOwnerA['connection_id']) $currentOwnerAToken = $connection['access_token'];
    mariaAssert(is_string($currentOwnerAToken),'Could not identify the final Owner A credential.');
    $service->revoke($currentOwnerAToken,time());
    mariaAssert(($service->status('shared-mariadb-project',101)['status'] ?? null) === 'revoked','Owner A revocation was not persisted.');
    mariaAssert(($service->status('shared-mariadb-project',202)['status'] ?? null) === 'active','Owner A revoke changed Owner B status.');
    $service->inspect($ownerBToken,time());

    $stage = 'concurrent token rotation for one owner';
    $rotationOwner = 303;
    $rotationSeed = $runBatch([['owner'=>$rotationOwner,'payload'=>$makePairing($rotationOwner)]]);
    $rotationSource = $rotationSeed[0]['access_token'];
    $rotationResults = $runBatch([
        ['owner'=>$rotationOwner,'action'=>'rotate','payload'=>$rotationSource],
        ['owner'=>$rotationOwner,'action'=>'rotate','payload'=>$rotationSource],
    ], true);
    $rotationWinners = array_values(array_filter($rotationResults, static fn(array $result): bool => $result['ok'] === true));
    $rotationLosers = array_values(array_filter($rotationResults, static fn(array $result): bool => $result['ok'] === false));
    mariaAssert(count($rotationWinners) === 1,'Concurrent rotation produced ' . count($rotationWinners) . ' successful rotations instead of exactly one.');
    mariaAssert(count($rotationLosers) === 1,'Concurrent rotation did not reject the losing request.');
    mariaAssert($rotationLosers[0]['error'] !== '','Losing rotation failed without a diagnostic.');
    $service->inspect($rotationWinners[0]['access_token'],time());
    try { $service->inspect($rotationSource,time()); throw new RuntimeException('Rotated source token remained valid.'); }
    catch (RuntimeException $exception) { if ($exception->getMessage() === 'Rotated source token remained valid.') throw $exception; }
    $rotationActive = (int) $projectPdo->query('SELECT COUNT(*) FROM devpilot_connector_connections WHERE project_id=\'shared-mariadb-project\' AND owner_id=' . $rotationOwner . ' AND status=\'active\'')->fetchColumn();
    mariaAssert($rotationActive === 1,'Concurrent rotation left ' . $rotationActive . ' active connections for the rotating owner.');
    mariaAssert(($service->status('shared-mariadb-project',202)['status'] ?? null) === 'active','Owner rotation changed an unrelated owner.');
    mariaAssert(($service->status('shared-mariadb-project',101)['status'] ?? null) === 'revoked','Owner rotation resurrected an unrelated revoked owner.');

    $hubRollback = file_get_contents($root . '/migrations/006_devpilot_hub_registry.rollback.sql');
    $projectOwnerRollback = file_get_contents($root . '/migrations/005_devpilot_project_owner.rollback.sql');
    $projectConnectorRollback = file_get_contents($root . '/migrations/004_devpilot_connector.rollback.sql');
    foreach ([[$hubPdo,$hubRollback],[$projectPdo,$projectOwnerRollback],[$projectPdo,$projectConnectorRollback]] as [$database,$sql]) {
        if (!is_string($sql)) throw new RuntimeException('Could not read a connector rollback migration.');
        $sql = preg_replace('/^[ \t]*--[^\r\n]*\R?/m', '', $sql) ?? $sql;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) $database->exec($statement);
    }
    mariaAssert((int) $hubPdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('devpilot_hub_pairing_requests','devpilot_connected_projects')")->fetchColumn() === 0,'Hub rollback left connector tables behind.');
    mariaAssert((int) $projectPdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('devpilot_connector_pairings','devpilot_connector_connections','devpilot_connector_attempts')")->fetchColumn() === 0,'Project rollback left connector tables behind.');

    $stage = 're-applying forward migrations after rollback';
    foreach ([[$projectPdo,$connectorMigration],[$projectPdo,$ownerMigration],[$hubPdo,$hubMigration]] as [$database,$sql]) {
        foreach (array_filter(array_map('trim', explode(';', (string) $sql))) as $statement) $database->exec($statement);
    }
    mariaAssert((int) $projectPdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_connector_connections' AND COLUMN_NAME='owner_id'")->fetchColumn() === 1,'Re-applied migration 005 did not restore the owner column.');
    mariaAssert((int) $projectPdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_connector_connections' AND INDEX_NAME='idx_connector_owner_project'")->fetchColumn() > 0,'Re-applied migration 005 did not restore the owner/project index.');
    mariaAssert((int) $hubPdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('devpilot_hub_pairing_requests','devpilot_connected_projects')")->fetchColumn() === 2,'Re-applied Hub migration did not restore its tables.');

    $successMessage = 'PASS MariaDB connector concurrency: real migrations 004/005 on project DB and 006 on separate Hub DB; existing-record invalidation; concurrent two-owner creation, same-owner reconnect and fail-closed token rotation; stale token rejection; owner-isolated revocation; rollback then clean forward re-application';
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL MariaDB connector concurrency during ' . $stage . ': ' . $exception->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach ($workers as $worker) if (is_resource($worker['process'])) { proc_terminate($worker['process']); proc_close($worker['process']); }
    $projectPdo = null;
    $hubPdo = null;
    if ($adminPdo instanceof PDO) foreach ($databaseNames as $databaseName) {
        try { $adminPdo->exec('DROP DATABASE IF EXISTS `' . $databaseName . '`'); } catch (Throwable $exception) { $cleanupErrors[] = $databaseName . ': ' . $exception->getMessage(); }
    }
    $adminPdo = null;
    if (is_dir($temporaryDirectory)) {
        foreach (glob($temporaryDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) if (is_file($file)) @unlink($file);
        @rmdir($temporaryDirectory);
    }
}
if ($cleanupErrors !== []) { fwrite(STDERR, 'FAIL MariaDB test cleanup: ' . implode('; ', $cleanupErrors) . "\n"); exit(1); }
if (isset($exitCode)) exit($exitCode);
if (is_string($successMessage)) echo $successMessage . "\n";
