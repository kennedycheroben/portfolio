<?php
declare(strict_types=1);

namespace DevPilot;

use OutOfBoundsException;

final class Config
{
    private static ?string $devpilotRoot = null;
    private static ?string $registryPath = null;
    private static array $cache = [];

    public static function getRoot(): string
    {
        if (self::$devpilotRoot === null) {
            self::$devpilotRoot = dirname(__DIR__);
        }
        return self::$devpilotRoot;
    }

    public static function getRegistryPath(): string
    {
        if (self::$registryPath === null) {
            self::$registryPath = self::getRoot() . '/registry/projects.json';
        }
        return self::$registryPath;
    }

    public static function ensureDirectories(): void
    {
        $dirs = [
            dirname(self::getRegistryPath()),
            self::getRoot() . '/logs',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
                    throw new \RuntimeException('DevPilot runtime directory could not be created');
                }
            }
            if (!is_writable($dir)) {
                throw new \RuntimeException('DevPilot runtime directory is not writable');
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $envValue = getenv(strtoupper($key));
        if ($envValue !== false) {
            self::$cache[$key] = $envValue;
            return $envValue;
        }

        $configFile = self::getRoot() . '/config.json';
        if (is_readable($configFile) && !isset(self::$cache['_config'])) {
            self::$cache['_config'] = json_decode(file_get_contents($configFile), true) ?: [];
        }

        $value = self::$cache['_config'][$key] ?? $default;
        self::$cache[$key] = $value;
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$cache[$key] = $value;
    }

    public static function validatePath(string $path): string
    {
        if (empty($path)) {
            throw new OutOfBoundsException('Path cannot be empty');
        }

        $realPath = realpath($path);
        if ($realPath === false) {
            $normalized = normalizePath($path);
            if (!is_dir($normalized)) {
                throw new OutOfBoundsException("Path does not exist: {$path}");
            }
            $realPath = $normalized;
        }

        $normalized = normalizePath($path);
        if (!str_contains($normalized, $path) && !str_starts_with($normalized, $realPath)) {
            throw new OutOfBoundsException('Path traversal detected');
        }

        return $realPath ?: $normalized;
    }
}

function normalizePath(string $path): string
{
    $path = str_replace(['\\', '//'], '/', $path);
    $path = trim($path, '/');

    $parts = explode('/', $path);
    $resolved = [];

    foreach ($parts as $part) {
        if ($part === '.' || $part === '') {
            continue;
        }
        if ($part === '..') {
            array_pop($resolved);
            continue;
        }
        $resolved[] = $part;
    }

    return '/' . implode('/', $resolved);
}
