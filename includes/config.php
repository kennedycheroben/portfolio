<?php
declare(strict_types=1);

/** Load environment values without providing credential fallbacks. */
$loadEnvironmentFile = static function (string $environmentFile): void {
    if (!is_readable($environmentFile)) {
        return;
    }

    foreach (file($environmentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key) || getenv($key) !== false) {
            continue;
        }
        $value = trim($value, "\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
};

$explicitEnvironmentFile = getenv('APP_ENV_FILE');
if ($explicitEnvironmentFile) {
    $loadEnvironmentFile($explicitEnvironmentFile);
} else {
    $loadEnvironmentFile(dirname(__DIR__) . '/.env');
    if (getenv('APP_ENV') !== 'production') {
        $loadEnvironmentFile(dirname(__DIR__) . '/.env.local');
    }
}
