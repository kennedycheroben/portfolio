<?php
declare(strict_types=1);

namespace DevPilot;

use OutOfBoundsException;

final class Discovery
{
    private const MARKERS = [
        'php' => ['composer.json'],
        'laravel' => ['artisan', 'bootstrap/app.php'],
        'symfony' => ['symfony.lock', 'bin/console'],
        'django' => ['manage.py', 'settings.py'],
        'flask' => ['app.py', 'flask.py'],
        'fastapi' => ['main.py', 'fastapi.py'],
        'vue' => ['vue.config.js', 'vite.config.js', 'vue.config.ts'],
        'react' => ['react-scripts', 'react-app'],
        'javascript' => ['package.json', 'pnpm-lock.yaml', 'yarn.lock'],
        'typescript' => ['tsconfig.json', 'tsconfig.app.json'],
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
        '.git', 'vendor', 'node_modules', '.cache', 'build', 'dist',
        '.idea', '.vscode', '__pycache__', '.pytest_cache', 'coverage',
        'var/cache', 'var/log', 'storage/framework', 'bootstrap/cache',
        'target', 'bin', 'obj', '.git', 'node_modules', 'vendor',
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
        $languages = [
            'php' => ['composer.json', 'artisan', 'php', 'phtml'],
            'typescript' => ['tsconfig.json', 'tsconfig.app.json', 'ts', 'tsx'],
            'javascript' => ['package.json', 'js', 'jsx', 'mjs', 'cjs'],
            'python' => ['requirements.txt', 'pyproject.toml', 'py', 'setup.py'],
            'java' => ['pom.xml', 'build.gradle', 'java'],
            'go' => ['go.mod', 'go.sum', 'go'],
            'rust' => ['Cargo.toml', 'rs'],
            'csharp' => ['.csproj', '.sln', 'cs'],
            'ruby' => ['Gemfile', 'rb', 'rakefile'],
        ];

        // Count evidence for each language
        $languageScores = [];

        foreach ($languages as $lang => $indicators) {
            $score = 0;
            foreach ($indicators as $indicator) {
                if (in_array($indicator, $fileNames, true)) {
                    $score += 10;
                }
                if (self::findFile($paths, $indicator) !== null) {
                    $score += 5;
                }
                // Count file extensions
                $extCount = 0;
                foreach ($fileNames as $fn) {
                    if (str_ends_with($fn, '.' . $indicator) || ($indicator !== 'composer.json' && $indicator !== 'package.json' && str_contains($fn, '.' . $indicator))) {
                        $extCount++;
                    }
                }
                $score += min($extCount * 2, 50);
            }

            if ($score > 0) {
                $languageScores[$lang] = $score;
            }
        }

        if (empty($languageScores)) {
            return 'unknown';
        }

        // Return language with highest score
        arsort($languageScores);
        return array_key_first($languageScores);
    }
}