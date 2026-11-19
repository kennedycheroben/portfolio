<?php
declare(strict_types=1);

namespace DevPilot;

use OutOfBoundsException;

final class Discovery
{
    private const MARKERS = [
        'php' => ['composer.json', 'artisan', '.php'],
        'javascript' => ['package.json', 'pnpm-lock.yaml', 'yarn.lock'],
        'typescript' => ['package.json', 'tsconfig.json', 'tsc.config.json'],
        'python' => ['requirements.txt', 'pyproject.toml', 'setup.py', 'Pipfile'],
        'laravel' => ['artisan', 'composer.json', 'bootstrap/app.php'],
        'vue' => ['package.json', 'vue.config.js', 'vite.config.js'],
        'react' => ['package.json', 'tsconfig.json', '.eslintrc'],
        'node' => ['package.json'],
        'dockerfile' => ['Dockerfile', 'docker-compose.yml'],
        'mysql' => ['.env', '.env.local', 'database.mysql', 'my.cnf'],
        'postgresql' => ['.env', '.env.local', 'database.pg', '.pgpass', 'postgresql.conf'],
        'supabase' => ['supabase/config.toml', 'supabase/migrations'],
        'git' => ['.git'],
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
        ];

        $scanResult = self::scanDirectory($validatedPath, 10);
        $files = $scanResult['files'];
        $filePathList = $scanResult['filePaths'];

        foreach (self::MARKERS as $technology => $markers) {
            foreach ($markers as $marker) {
                if (in_array($marker, $files, true)) {
                    if (self::isFramework($technology)) {
                        $detected['frameworks'][] = $technology;
                    } elseif (self::isDatabase($technology)) {
                        $detected['databases'][] = $technology;
                    } else {
                        $detected['technologies'][] = $technology;
                    }
                    break;
                }

                $markerPath = self::findFile($filePathList, $marker);
                if ($markerPath !== null) {
                    if (self::isFramework($technology)) {
                        $detected['frameworks'][] = $technology;
                    } elseif (self::isDatabase($technology)) {
                        $detected['databases'][] = $technology;
                    } else {
                        $detected['technologies'][] = $technology;
                    }
                    break;
                }
            }
        }

        $detected['hasGit'] = in_array('.git', $files, true);

        $detected['primaryLanguage'] = self::detectPrimaryLanguage($filePathList, $files);
        $detected['hasDocker'] = in_array('Dockerfile', $files, true) || in_array('docker-compose.yml', $files, true);

        return $detected;
    }

    private static function scanDirectory(string $path, int $depth = 5, int $currentDepth = 0): array
    {
        $result = ['files' => [], 'filePaths' => []];

        if (!is_dir($path)) {
            return $result;
        }

        $iterator = new \DirectoryIterator($path);
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }

            $fileName = $fileInfo->getFilename();
            $fullPath = $fileInfo->getPathname();

            $result['files'][] = $fileName;
            $result['filePaths'][] = $fullPath;

            if ($currentDepth < $depth && $fileInfo->isDir()) {
                if (str_starts_with($fileName, '.') && $fileName !== '.git') {
                    continue;
                }
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
        return in_array($technology, ['laravel', 'vue', 'react', 'symfony', 'django', 'flask', 'fastapi'], true);
    }

    private static function isDatabase(string $technology): bool
    {
        return in_array($technology, ['mysql', 'postgresql', 'supabase', 'mariadb', 'sqlite', 'mongodb'], true);
    }

    private static function detectPrimaryLanguage(array $paths, array $fileNames): string
    {
        $languages = ['php', 'javascript', 'typescript', 'python', 'java', 'go', 'rust', 'csharp'];
        $indicators = [
            'php' => ['composer.json', 'artisan', '.php'],
            'javascript' => ['package.json', 'index.js', 'app.js'],
            'typescript' => ['package.json', 'tsconfig.json', '.ts'],
            'python' => ['requirements.txt', 'pyproject.toml', '.py', 'setup.py'],
            'java' => ['pom.xml', 'build.gradle', '.java'],
            'go' => ['go.mod', 'go.sum', '.go'],
            'rust' => ['Cargo.toml', '.rs'],
            'csharp' => ['.csproj', '.sln'],
        ];

        foreach ($languages as $lang) {
            foreach ($indicators[$lang] as $indicator) {
                if (in_array($indicator, $fileNames, true)) {
                    return $lang;
                }
                if (self::findFile($paths, $indicator) !== null) {
                    return $lang;
                }
            }
        }

        return 'unknown';
    }
}