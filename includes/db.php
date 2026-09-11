<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$databaseOptional = defined('DB_OPTIONAL') && DB_OPTIONAL === true;
$missingVariables = [];
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $variable) {
    $value = getenv($variable);
    $allowsEmptyLocalPassword = $variable === 'DB_PASS' && getenv('APP_ENV') !== 'production';
    if ($value === false || ($value === '' && !$allowsEmptyLocalPassword)) {
        $missingVariables[] = $variable;
    }
}

if ($missingVariables) {
    error_log('Portfolio configuration error: missing database environment variables.');
    if ($databaseOptional) {
        $pdo = null;
        return;
    }
    http_response_code(503);
    exit('This service is temporarily unavailable.');
}

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_NAME'));
    $pdo = new PDO($dsn, (string) getenv('DB_USER'), (string) getenv('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
} catch (PDOException $exception) {
    error_log('Portfolio database connection failed: ' . $exception->getMessage());
    if ($databaseOptional) {
        $pdo = null;
        return;
    }
    http_response_code(503);
    exit('This service is temporarily unavailable.');
}
