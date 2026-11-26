<?php
declare(strict_types=1);

function devpilot_root(): string
{
    $configuredRoot = getenv('DEVPILOT_ROOT');
    $production = getenv('APP_ENV') === 'production';
    if (!is_string($configuredRoot) || trim($configuredRoot) === '') {
        if ($production) {
            throw new RuntimeException('Configure DEVPILOT_ROOT outside the public document root.');
        }
        $configuredRoot = dirname(__DIR__) . '/DevPilot';
    } else {
        $configuredRoot = trim($configuredRoot);
    }

    $isAbsolute = DIRECTORY_SEPARATOR === '\\'
        ? preg_match('/^(?:[A-Za-z]:[\\\\\/]|\\\\\\\\)/', $configuredRoot) === 1
        : str_starts_with($configuredRoot, '/');
    if ($production && !$isAbsolute) {
        throw new RuntimeException('Configure DEVPILOT_ROOT as an absolute private path.');
    }

    $root = realpath($configuredRoot);
    if ($root === false || !is_dir($root) || !is_file($root . '/autoload.php')) {
        throw new RuntimeException('DevPilot private application files are unavailable.');
    }

    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($production && $documentRoot !== false) {
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        $normalizedDocumentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        if (DIRECTORY_SEPARATOR === '\\') {
            $normalizedRoot = strtolower($normalizedRoot);
            $normalizedDocumentRoot = strtolower($normalizedDocumentRoot);
        }
        if ($normalizedRoot === $normalizedDocumentRoot
            || str_starts_with($normalizedRoot, $normalizedDocumentRoot . '/')) {
            throw new RuntimeException('DEVPILOT_ROOT must be outside the public document root.');
        }
    }

    return $root;
}

function devpilot_register_autoloader(): void
{
    require_once devpilot_root() . '/autoload.php';
}
