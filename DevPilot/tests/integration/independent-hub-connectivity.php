<?php
declare(strict_types=1);

use DevPilot\Connector\ConnectedProjectStore;
use DevPilot\Connector\HubConnectorService;
use DevPilot\Connector\HubPairingRepository;
use DevPilot\Connector\PdoConnectorStore;
use DevPilot\Connector\ProjectConnectorService;
use DevPilot\Website\CredentialVault;
use DevPilot\Website\VaultKeyring;

require_once dirname(__DIR__,2) . '/autoload.php';

function integrationAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function freePort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if (!is_resource($socket)) throw new RuntimeException('Could not allocate a test port.');
    $address = stream_socket_get_name($socket,false);
    fclose($socket);
    return (int) substr(strrchr((string)$address,':'),1);
}
function makeAdminSession(string $directory, int $userId, string $csrf): array
{
    ini_set('session.save_path',$directory);
    session_name('kennedy_portfolio');
    $id = 'dvp' . bin2hex(random_bytes(16));
    session_id($id);
    session_start();
    $_SESSION = ['user_id'=>$userId,'user_role'=>'admin','csrf_token'=>$csrf];
    session_write_close();
    return ['id'=>$id,'csrf'=>$csrf];
}
function spawn(array $command, string $log, string $cwd, array $environment): array
{
    $process = proc_open($command,[0=>['pipe','r'],1=>['file',$log,'ab'],2=>['file',$log,'ab']],$pipes,$cwd,$environment);
    if (!is_resource($process)) throw new RuntimeException('Could not start isolated test server.');
    fclose($pipes[0]);
    return ['process'=>$process,'log'=>$log];
}
function waitForPort(int $port, array $server): void
{
    for ($i=0;$i<80;$i++) {
        $socket=@fsockopen('127.0.0.1',$port,$errno,$error,0.15);
        if (is_resource($socket)) { fclose($socket); return; }
        $status=proc_get_status($server['process']);
        if (!$status['running']) throw new RuntimeException('Isolated server exited: ' . (file_get_contents($server['log']) ?: 'no server log'));
        usleep(100000);
    }
    throw new RuntimeException('Isolated server did not become ready.');
}
function waitForTls(int $port, string $ca, array $server): void
{
    $context=stream_context_create(['ssl'=>['cafile'=>$ca,'verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>'localhost']]);
    for($i=0;$i<50;$i++) {
        $socket=@stream_socket_client('tls://127.0.0.1:' . $port,$errno,$error,0.3,STREAM_CLIENT_CONNECT,$context);
        if(is_resource($socket)){fclose($socket);return;}
        $status=proc_get_status($server['process']);
        if(!$status['running']) throw new RuntimeException('TLS proxy exited (' . $status['exitcode'] . '): ' . (file_get_contents($server['log']) ?: 'no TLS proxy log'));
        usleep(100000);
    }
    throw new RuntimeException('TLS proxy did not become ready: ' . (file_get_contents($server['log']) ?: 'no TLS proxy log'));
}
function testRequest(string $url, string $ca, array $fields = [], ?array $session = null, ?string $bearer = null, bool $json = false): array
{
    $headers=['Accept: application/json'];
    $parsed=parse_url($url);
    $options=[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CAINFO=>$ca,CURLOPT_RESOLVE=>['localhost:' . (int)$parsed['port'] . ':127.0.0.1']];
    if ($json) { $body=json_encode($fields,JSON_THROW_ON_ERROR); $headers[]='Content-Type: application/json'; $options[CURLOPT_POSTFIELDS]=$body; }
    else { $options[CURLOPT_POSTFIELDS]=http_build_query($fields); $headers[]='Content-Type: application/x-www-form-urlencoded'; }
    if ($session !== null) $headers[]='Cookie: kennedy_portfolio=' . $session['id'];
    if ($bearer !== null) $headers[]='Authorization: Bearer ' . $bearer;
    $options[CURLOPT_POST]=true; $options[CURLOPT_HTTPHEADER]=$headers;
    $curl=curl_init(); curl_setopt_array($curl,$options); $raw=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); $headerSize=(int)curl_getinfo($curl,CURLINFO_HEADER_SIZE); $error=curl_error($curl); curl_close($curl);
    if (!is_string($raw)) throw new RuntimeException('TLS request failed: ' . $error);
    $rawHeaders=substr($raw,0,$headerSize); $body=substr($raw,$headerSize); $decoded=json_decode($body,true);
    return ['status'=>$status,'headers'=>$rawHeaders,'body'=>$decoded,'raw_body'=>$body];
}
function createProjectSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE devpilot_connector_pairings (id INTEGER PRIMARY KEY AUTOINCREMENT,code_hash BLOB UNIQUE NOT NULL,project_id TEXT NOT NULL,project_name TEXT NOT NULL,origin TEXT NOT NULL,scopes_json TEXT NOT NULL,expires_at INTEGER NOT NULL,used_at INTEGER NULL,created_at INTEGER NOT NULL DEFAULT (unixepoch()),owner_id INTEGER NOT NULL DEFAULT 0)');
    $pdo->exec('CREATE TABLE devpilot_connector_connections (id INTEGER PRIMARY KEY AUTOINCREMENT,connection_id TEXT UNIQUE NOT NULL,project_id TEXT NOT NULL,project_name TEXT NOT NULL,origin TEXT NOT NULL,scopes_json TEXT NOT NULL,token_hash BLOB UNIQUE NOT NULL,status TEXT NOT NULL,connected_at INTEGER NOT NULL,last_seen_at INTEGER NULL,expires_at INTEGER NOT NULL,revoked_at INTEGER NULL,owner_id INTEGER NOT NULL DEFAULT 0)');
    $pdo->exec('CREATE INDEX idx_connector_owner_project ON devpilot_connector_connections(owner_id,project_id,status)');
    $pdo->exec('CREATE TABLE devpilot_connector_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT,ip_hash BLOB NOT NULL,attempted_at INTEGER NOT NULL)');
}
function createHubSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE devpilot_hub_pairing_requests (id INTEGER PRIMARY KEY AUTOINCREMENT,request_id TEXT UNIQUE NOT NULL,pairing_code_hash BLOB UNIQUE NOT NULL,project_id TEXT NOT NULL,project_name TEXT NOT NULL,origin TEXT NOT NULL,scopes_json TEXT NOT NULL,return_url TEXT NOT NULL,request_ip_hash BLOB NOT NULL,status TEXT NOT NULL,owner_id INTEGER NULL,credential_uuid BLOB NULL,cipher_algorithm TEXT NULL,data_key_version TEXT NULL,master_key_version TEXT NULL,payload_nonce BLOB NULL,payload_ciphertext BLOB NULL,wrapped_dek_nonce BLOB NULL,wrapped_dek BLOB NULL,expires_at INTEGER NOT NULL,created_at INTEGER NOT NULL,updated_at INTEGER NOT NULL)');
    $pdo->exec('CREATE INDEX idx_hub_pair_status_expiry ON devpilot_hub_pairing_requests(status,expires_at)');
    $pdo->exec('CREATE INDEX idx_hub_pair_ip_created ON devpilot_hub_pairing_requests(request_ip_hash,created_at)');
    $pdo->exec('CREATE TABLE devpilot_connected_projects (id INTEGER PRIMARY KEY AUTOINCREMENT,owner_id INTEGER NOT NULL,project_id TEXT NOT NULL,connection_id TEXT UNIQUE NOT NULL,project_name TEXT NOT NULL,origin TEXT NOT NULL,scopes_json TEXT NOT NULL,status TEXT NOT NULL,credential_uuid BLOB NOT NULL,cipher_algorithm TEXT NOT NULL,data_key_version TEXT NOT NULL,master_key_version TEXT NOT NULL,payload_nonce BLOB NOT NULL,payload_ciphertext BLOB NOT NULL,wrapped_dek_nonce BLOB NOT NULL,wrapped_dek BLOB NOT NULL,connected_at INTEGER NOT NULL,last_seen_at INTEGER NULL,last_inspected_at INTEGER NULL,inspection_json TEXT NULL,last_error TEXT NULL,created_at INTEGER NOT NULL DEFAULT (unixepoch()),updated_at INTEGER NOT NULL,UNIQUE(owner_id,project_id))');
    $pdo->exec('CREATE INDEX idx_connected_project_status ON devpilot_connected_projects(owner_id,status)');
}
function removeTree(string $path, string $expectedPrefix): void
{
    $real=realpath($path);
    $temp=realpath(sys_get_temp_dir());
    if ($real===false || $temp===false || !str_starts_with($real,$temp . DIRECTORY_SEPARATOR) || !str_starts_with(basename($real),$expectedPrefix)) return;
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($real);
}

$root=dirname(__DIR__,3);
$temp=sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devpilot-hub-e2e-' . bin2hex(random_bytes(6));
$servers=[];
$hubDb=null; $projectDb=null; $hubRegistry=null; $hubVault=null;
try {
    if (!extension_loaded('sodium') || !extension_loaded('curl') || !extension_loaded('openssl') || !in_array('sqlite',PDO::getAvailableDrivers(),true)) throw new RuntimeException('Integration requirements missing: enable sodium, curl, openssl and pdo_sqlite for the PHP CLI.');
    if (!mkdir($temp,0700,true)) throw new RuntimeException('Could not create isolated test directory.');
    $sessionsHub=$temp . '/hub-sessions'; $sessionsProject=$temp . '/project-sessions'; mkdir($sessionsHub,0700); mkdir($sessionsProject,0700);
    $hubDbPath=$temp . '/hub.sqlite'; $projectDbPath=$temp . '/project.sqlite';
    $hubDb=new PDO('sqlite:' . $hubDbPath); $hubDb->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); createHubSchema($hubDb);
    $projectDb=new PDO('sqlite:' . $projectDbPath); $projectDb->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); createProjectSchema($projectDb);

    $opensslBinary='C:/xampp/apache/bin/openssl.exe';
    if (!is_file($opensslBinary)) throw new RuntimeException('XAMPP OpenSSL is required to generate the isolated TLS test certificate.');
    $caPath=$temp . '/localhost-ca.pem'; $privateKeyPath=$temp . '/localhost-key.pem'; $certAndKeyPath=$temp . '/localhost-cert-key.pem'; $opensslConfig=$temp . '/openssl.cnf';
    file_put_contents($opensslConfig,"[req]\ndistinguished_name=dn\nprompt=no\nx509_extensions=v3_req\n[dn]\nCN=localhost\n[v3_req]\nsubjectAltName=DNS:localhost\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,digitalSignature,keyEncipherment,keyCertSign\n");
    $certificateProcess=proc_open([$opensslBinary,'req','-x509','-newkey','rsa:2048','-nodes','-keyout',$privateKeyPath,'-out',$caPath,'-days','2','-subj','/CN=localhost','-config',$opensslConfig],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$certificatePipes,$temp);
    if (!is_resource($certificateProcess)) throw new RuntimeException('Could not start OpenSSL for the isolated TLS certificate.');
    fclose($certificatePipes[0]); $certificateOutput=stream_get_contents($certificatePipes[1]); fclose($certificatePipes[1]); $certificateError=stream_get_contents($certificatePipes[2]); fclose($certificatePipes[2]);
    $certificateExit=proc_close($certificateProcess);
    if ($certificateExit!==0 || !is_file($caPath) || !is_file($privateKeyPath)) throw new RuntimeException('Could not generate isolated TLS certificate: ' . trim((string)$certificateError . ' ' . (string)$certificateOutput));
    file_put_contents($certAndKeyPath,file_get_contents($caPath) . file_get_contents($privateKeyPath));
    $keyringPath=$temp . '/keyring.json'; $key=random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    file_put_contents($keyringPath,json_encode(['active_version'=>'integration','keys'=>['integration'=>base64_encode($key)]],JSON_THROW_ON_ERROR));
    putenv('DEVPILOT_VAULT_KEYRING_FILE=' . $keyringPath);

    $hubHttpPort=freePort(); $projectHttpPort=freePort(); $hubTlsPort=freePort(); $projectTlsPort=freePort();
    $hubOrigin='https://localhost:' . $hubTlsPort; $projectOrigin='https://localhost:' . $projectTlsPort;
    $baseEnv=getenv();
    $common=['APP_ENV'=>'staging','DEVPILOT_CONNECTOR_TEST_MODE'=>'1','DEVPILOT_CONNECTOR_TEST_PORTS'=>$hubTlsPort . ',' . $projectTlsPort,'DEVPILOT_CONNECTOR_TEST_CA'=>$caPath,'DEVPILOT_VAULT_KEYRING_FILE'=>$keyringPath];
    $hubEnv=array_merge($baseEnv,$common,['APP_URL'=>$hubOrigin,'DEVPILOT_HUB_URL'=>$hubOrigin,'DB_DSN'=>'sqlite:' . $hubDbPath,'SESSION_SAVE_PATH'=>$sessionsHub]);
    $projectEnv=array_merge($baseEnv,$common,['APP_URL'=>$projectOrigin,'DEVPILOT_HUB_URL'=>$hubOrigin,'DB_DSN'=>'sqlite:' . $projectDbPath,'SESSION_SAVE_PATH'=>$sessionsProject]);
    $php=PHP_BINARY;
    $hubApp=spawn([$php,'-d','extension=php_sodium.dll','-d','session.save_path=' . $sessionsHub,'-S','127.0.0.1:' . $hubHttpPort,'-t',$root],$temp . '/hub-app.log',$root,$hubEnv); $servers[]=$hubApp;
    $projectApp=spawn([$php,'-d','extension=php_sodium.dll','-d','session.save_path=' . $sessionsProject,'-S','127.0.0.1:' . $projectHttpPort,'-t',$root],$temp . '/project-app.log',$root,$projectEnv); $servers[]=$projectApp;
    waitForPort($hubHttpPort,$hubApp); waitForPort($projectHttpPort,$projectApp);
    $hubProxy=spawn([$php,$root . '/DevPilot/tests/integration/tls-proxy.php',(string)$hubTlsPort,(string)$hubHttpPort,$certAndKeyPath],$temp . '/hub-tls.log',$root,$hubEnv); $servers[]=$hubProxy;
    $projectProxy=spawn([$php,$root . '/DevPilot/tests/integration/tls-proxy.php',(string)$projectTlsPort,(string)$projectHttpPort,$certAndKeyPath],$temp . '/project-tls.log',$root,$projectEnv); $servers[]=$projectProxy;
    waitForTls($hubTlsPort,$caPath,$hubProxy);
    waitForTls($projectTlsPort,$caPath,$projectProxy);

    $projectAdmin=makeAdminSession($sessionsProject,11,bin2hex(random_bytes(16)));
    $hubAdmin=makeAdminSession($sessionsHub,99,bin2hex(random_bytes(16)));
    $connect=testRequest($projectOrigin . '/admin/api/devpilot-connector.php',$caPath,['csrf_token'=>$projectAdmin['csrf'],'action'=>'connect'],$projectAdmin);
    integrationAssert($connect['status']===200 && ($connect['body']['success']??false)===true,'Project administrator could not start Hub pairing: ' . $connect['raw_body']);
    integrationAssert(str_starts_with((string)($connect['body']['approval_url']??''),$hubOrigin . '/admin/devpilot-connector-approve.php?request='),'Project used an unexpected Hub approval URL.');

    $approvalUrl=(string)$connect['body']['approval_url'];
    parse_str((string)parse_url($approvalUrl,PHP_URL_QUERY),$query);
    $approved=testRequest($hubOrigin . '/admin/devpilot-connector-approve.php?request=' . rawurlencode((string)($query['request']??'')),$caPath,['request_id'=>$query['request']??'','action'=>'approve','csrf_token'=>$hubAdmin['csrf']],$hubAdmin);
    integrationAssert($approved['status']===303 && str_contains($approved['headers'],$projectOrigin . '/admin/devpilot-websites.php?connector=connected'),'Hub admin approval did not complete or return to the project.');

    $projectId='php-' . substr(hash('sha256',$projectOrigin . '|' . $root),0,24);
    $hubVault=new CredentialVault(VaultKeyring::fromEnvironment());
    $hubRegistry=new ConnectedProjectStore($hubDb,$hubVault);
    $registered=$hubRegistry->status(99,$projectId);
    integrationAssert(is_array($registered) && $registered['status']==='active','Hub did not persist the owner-bound project record.');
    integrationAssert(($registered['inspection']['connection_health']??'')==='healthy' && isset($registered['last_inspected_at']),'Hub did not persist an authenticated inspection.');
    integrationAssert($hubRegistry->status(98,$projectId)===null,'A different Hub owner could read the project record.');
    $token=$hubRegistry->token(99,$projectId);
    $cipher=$hubDb->query('SELECT payload_ciphertext FROM devpilot_connected_projects LIMIT 1')->fetchColumn();
    integrationAssert(is_string($cipher) && !hash_equals($cipher,$token),'Hub token was stored in plaintext.');
    $projectStatus=(new ProjectConnectorService(new PdoConnectorStore($projectDb)))->status($projectId,11);
    integrationAssert(is_array($projectStatus) && $projectStatus['status']==='active','Project did not persist Hub authorization.');

    sleep(1);
    $refresh=testRequest($hubOrigin . '/admin/api/devpilot-connected-projects.php',$caPath,['csrf_token'=>$hubAdmin['csrf'],'action'=>'refresh','project_id'=>$projectId],$hubAdmin);
    integrationAssert($refresh['status']===200 && ($refresh['body']['success']??false)===true,'Hub refresh did not perform authenticated inspection.');
    $refreshed=$hubRegistry->status(99,$projectId);
    integrationAssert((int)$refreshed['last_inspected_at']>(int)$registered['last_inspected_at'],'Refresh did not persist a newer inspection timestamp.');

    $disconnect=testRequest($hubOrigin . '/admin/api/devpilot-connected-projects.php',$caPath,['csrf_token'=>$hubAdmin['csrf'],'action'=>'disconnect','project_id'=>$projectId],$hubAdmin);
    integrationAssert($disconnect['status']===200 && ($disconnect['body']['success']??false)===true,'Hub did not revoke the project token.');
    $revoked=(new ProjectConnectorService(new PdoConnectorStore($projectDb)))->status($projectId,11);
    integrationAssert(is_array($revoked) && $revoked['status']==='revoked','Project did not persist token revocation.');
    $oldTokenRequest=testRequest($projectOrigin . '/api/devpilot-connector.php?action=inspect',$caPath,[] ,null,$token,true);
    integrationAssert($oldTokenRequest['status']===403 && ($oldTokenRequest['body']['success']??true)===false,'Revoked bearer token was accepted.');

    echo "PASS independent Hub/customer TLS integration: pairing request, authenticated Hub approval, remote inspection, Hub persistence, owner isolation, refresh, revocation, and revoked-token rejection\n";
} catch (Throwable $exception) {
    foreach (glob($temp . '/*.log') ?: [] as $logPath) { $logContents = file_get_contents($logPath); if (is_string($logContents) && $logContents !== '') fwrite(STDERR, basename($logPath) . ":\n" . $logContents . "\n"); }
    throw $exception;
} finally {
    foreach (array_reverse($servers) as $server) if (is_resource($server['process'])) { proc_terminate($server['process']); proc_close($server['process']); }
    unset($hubRegistry,$hubVault); $hubDb=null; $projectDb=null; gc_collect_cycles();
    removeTree($temp,'devpilot-hub-e2e-');
}
