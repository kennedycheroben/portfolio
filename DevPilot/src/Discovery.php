<?php
declare(strict_types=1);

namespace DevPilot;

use OutOfBoundsException;

final class Discovery
{
    private const MARKERS = [
        'php' => ['composer.json', 'artisan', 'index.php'],
        'laravel' => ['artisan', 'bootstrap/app.php'],
        'symfony' => ['symfony.lock', 'bin/console'],
        'django' => ['manage.py', 'settings.py'],
        'flask' => ['app.py', 'flask.py'],
        'fastapi' => ['main.py', 'fastapi.py'],
        'vue' => ['vue.config.js', 'vite.config.js', 'vue.config.ts'],
        'react' => ['react-scripts', 'react-app'],
        'javascript' => ['package.json', 'pnpm-lock.yaml', 'yarn.lock'],
        'typescript' => ['tsconfig.json', 'tsconfig.app.json', '.ts', '.tsx'],
        'python' => ['requirements.txt', 'pyproject.toml', 'setup.py', 'Pipfile'],
        'node' => ['package.json'],
        'dockerfile' => ['Dockerfile', 'docker-compose.yml'],
        'mysql' => ['.env', '.env.local', 'database.mysql', 'my.cnf'],
        'postgresql' => ['.env', '.env.local', 'database.pg', '.pgpass', 'postgresql.conf'],
        'supabase' => ['supabase/config.toml', 'supabase/migrations'],
        'sqlite' => ['.sqlite', '.sqlite3', '.db'],
        'git' => ['.git'],
    ];

    private const EXCLUDE_DIRS = [
        'vendor', 'node_modules', '.cache', 'build', 'dist',
        '.idea', '.vscode', '__pycache__', '.pytest_cache', 'coverage',
        'var/cache', 'var/log', 'storage/framework', 'bootstrap/cache',
        'target', 'bin', 'obj', 'node_modules', 'vendor',
    ];

    public static function detect(string $path): array
    {
        $validatedPath = Config::validatePath($path);

        if (!is_dir($validatedPath)) {
            throw new OutOfBoundsException("Path is not a directory: {$path}");
        }

        $detected = [
            'technologies' => [],
            'frameworks' => [],
            'databases' => [],
            'hasGit' => false,
            'evidence' => [], // Track evidence for each detection
        ];

        $scanResult = self::scanDirectory($validatedPath, 5);
        $files = $scanResult['files'];
        $filePathList = $scanResult['filePaths'];

        foreach (self::MARKERS as $technology => $markers) {
            $matched = false;
            $matchedMarker = null;

            foreach ($markers as $marker) {
                if (in_array($marker, $files, true)) {
                    $matched = true;
                    $matchedMarker = $marker;
                    break;
                }

                // Extension-style markers (e.g. '.ts', '.tsx') match any file with that extension
                if (str_starts_with($marker, '.')) {
                    foreach ($files as $fn) {
                        if ($fn !== $marker && str_ends_with($fn, $marker)) {
                            $matched = true;
                            $matchedMarker = $marker;
                            break 2;
                        }
                    }
                }

                $markerPath = self::findFile($filePathList, $marker);
                if ($markerPath !== null) {
                    $matched = true;
                    $matchedMarker = $marker;
                    break;
                }
            }

            if ($matched) {
                if (self::isFramework($technology)) {
                    $detected['frameworks'][] = $technology;
                    $detected['evidence'][$technology] = 'Found ' . $matchedMarker;
                } elseif (self::isDatabase($technology)) {
                    $detected['databases'][] = $technology;
                    $detected['evidence'][$technology] = 'Found ' . $matchedMarker;
                } else {
                    $detected['technologies'][] = $technology;
                    $detected['evidence'][$technology] = 'Found ' . $matchedMarker;
                }
            }
        }

        $detected['hasGit'] = in_array('.git', $files, true);

        $detected['primaryLanguage'] = self::detectPrimaryLanguage($filePathList, $files);
        $detected['hasDocker'] = in_array('Dockerfile', $files, true) || in_array('docker-compose.yml', $files, true);

        return $detected;
    }

    private static function scanDirectory(string $path, int $depth = 3, int $currentDepth = 0): array
    {
        $result = ['files' => [], 'filePaths' => []];

        if (!is_dir($path)) {
            return $result;
        }

        if ($currentDepth >= $depth) {
            return $result;
        }

        $iterator = new \DirectoryIterator($path);
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }

            $fileName = $fileInfo->getFilename();
            $fullPath = $fileInfo->getPathname();

            // Skip excluded directories
            if ($fileInfo->isDir() && in_array($fileName, self::EXCLUDE_DIRS, true)) {
                continue;
            }

            $result['files'][] = $fileName;
            $result['filePaths'][] = $fullPath;

            if ($fileInfo->isDir() && $currentDepth < $depth) {
                $subdir = self::scanDirectory($fullPath, $depth, $currentDepth + 1);
                foreach ($subdir['files'] as $f) {
                    if (!in_array($f, $result['files'], true)) {
                        $result['files'][] = $f;
                    }
                }
                foreach ($subdir['filePaths'] as $fp) {
                    if (!in_array($fp, $result['filePaths'], true)) {
                        $result['filePaths'][] = $fp;
                    }
                }
            }
        }

        return $result;
    }

    private static function findFile(array $paths, string $filename): ?string
    {
        foreach ($paths as $path) {
            if (basename($path) === $filename) {
                return $path;
            }
            if (str_ends_with($path, '/' . $filename) || str_ends_with($path, '\\' . $filename)) {
                return $path;
            }
        }
        return null;
    }

    private static function isFramework(string $technology): bool
    {
        return in_array($technology, [
            'laravel', 'symfony', 'vue', 'react', 'django', 'flask', 'fastapi', 'spring', 'rails'
        ], true);
    }

    private static function isDatabase(string $technology): bool
    {
        return in_array($technology, ['mysql', 'postgresql', 'supabase', 'mariadb', 'sqlite', 'mongodb'], true);
    }

    private static function detectPrimaryLanguage(array $paths, array $fileNames): string
    {
        // Check for TypeScript first - check both file names and full paths
        $hasTsconfig = in_array('tsconfig.json', $fileNames, true) || self::findFile($paths, 'tsconfig.json') !== null;
        $hasTsFiles = false;

        // Check both file names and full paths for .ts/.tsx files
        foreach ($fileNames as $fn) {
            if (str_ends_with($fn, '.ts') || str_ends_with($fn, '.tsx')) {
                $hasTsFiles = true;
                break;
            }
        }
        // Also check full paths
        if (!$hasTsFiles) {
            foreach ($paths as $path) {
                if (str_ends_with($path, '.ts') || str_ends_with($path, '.tsx')) {
                    $hasTsFiles = true;
                    break;
                }
            }
        }

        if (in_array('tsconfig.json', $fileNames, true) || self::findFile($paths, 'tsconfig.json') !== null || $hasTsFiles) {
            return 'typescript';
        }

        // Check for strong JavaScript indicators
        $hasPackageJson = in_array('package.json', $fileNames, true) || self::findFile($paths, 'package.json') !== null;

        // Check for PHP
        $hasPhp = false;
        foreach ($fileNames as $fn) {
            if (str_ends_with($fn, '.php') || str_ends_with($fn, '.phtml')) {
                return 'php';
            }
        }
        foreach ($paths as $path) {
            if (str_ends_with($path, '.php') || str_ends_with($path, '.phtml')) {
                return 'php';
            }
        }
        if (in_array('composer.json', $fileNames, true) || in_array('artisan', $fileNames, true) ||
            self::findFile($paths, 'composer.json') !== null || self::findFile($paths, 'artisan') !== null) {
            return 'php';
        }

        // Check for Python
        foreach ($fileNames as $fn) {
            if (str_ends_with($fn, '.py')) {
                return 'python';
            }
        }
        foreach ($paths as $path) {
            if (str_ends_with($path, '.py')) {
                return 'python';
            }
        }
        if (in_array('requirements.txt', $fileNames, true) || in_array('pyproject.toml', $fileNames, true) ||
            in_array('setup.py', $fileNames, true) || in_array('Pipfile', $fileNames, true) ||
            self::findFile($paths, 'requirements.txt') !== null || self::findFile($paths, 'pyproject.toml') !== null) {
            return 'python';
        }

        // Check for JavaScript (but not TypeScript)
        if ($hasPackageJson || self::findFile($paths, 'package.json') !== null) {
            return 'javascript';
        }

        // Check for other languages by extension
        foreach ($paths as $path) {
            if (str_ends_with($path, '.java')) return 'java';
            if (str_ends_with($path, '.go')) return 'go';
            if (str_ends_with($path, '.rs')) return 'rust';
            if (str_ends_with($path, '.cs')) return 'csharp';
            if (str_ends_with($path, '.rb')) return 'ruby';
        }

        return 'unknown';
    }
}