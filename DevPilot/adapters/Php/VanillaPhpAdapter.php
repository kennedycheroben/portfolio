<?php
declare(strict_types=1);

namespace DevPilot\Adapters\Php;

use DevPilot\Adapters\AbstractAdapter;
use DevPilot\Project;

/**
 * Adapter for vanilla PHP projects (no framework).
 * Handles basic PHP projects without specific framework scaffolding.
 */
class VanillaPhpAdapter extends AbstractAdapter
{
    public static function getVersion(): string
    {
        return '1.0.0';
    }

    public static function getSupportedStack(): array
    {
        return [
            'php' => '>=8.0',
        ];
    }

    public static function supports(string $path): bool
    {
        if (!is_dir($path) || !is_readable($path)) {
            return false;
        }

        if (self::hasFrameworkFiles($path)) {
            return false;
        }

        foreach (glob(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $content = self::readPhpSource($file);
            if ($content !== null && preg_match('/<\?(?:php\b|=)/i', $content) === 1) return true;
        }
        return false;
    }

    private static function hasFrameworkFiles(string $path): bool
    {
        foreach (['artisan','bootstrap/app.php','symfony.lock','bin/console','spark','bin/cake','yii'] as $marker) {
            if (is_file(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $marker))) return true;
        }
        $manifestPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . 'composer.json';
        $manifest = is_file($manifestPath) && is_readable($manifestPath) ? @file_get_contents($manifestPath) : false;
        $composer = is_string($manifest) ? json_decode($manifest, true) : null;
        if (!is_array($composer)) return false;
        $packages = array_merge((array) ($composer['require'] ?? []), (array) ($composer['require-dev'] ?? []));
        foreach (['laravel/framework','symfony/framework-bundle','codeigniter4/framework','cakephp/cakephp','yiisoft/yii2','slim/slim','mezzio/mezzio','nette/application','laminas/laminas-mvc','drupal/core'] as $frameworkPackage) {
            if (array_key_exists($frameworkPackage, $packages)) return true;
        }
        return false;
    }

    private static function readPhpSource(string $file): ?string
    {
        if (!is_file($file) || !is_readable($file)) return null;
        $content = @file_get_contents($file);
        return is_string($content) ? $content : null;
    }

    public function getAvailableCommands(): array
    {
        return [
            'build' => false,
            'test' => false,
            'serve' => true,
            'lint' => false,
            'generateDemoData' => false,
        ];
    }

    public function discover(): array
    {
        $phpFiles = array_values(array_filter(glob($this->projectPath . DIRECTORY_SEPARATOR . '*.php') ?: [], 'is_file'));

        $detectedFeatures = [];
        foreach ($phpFiles as $file) {
            if (strpos(basename($file), 'api') !== false) {
                $detectedFeatures[] = 'api';
            }
            if (strpos(basename($file), 'admin') !== false) {
                $detectedFeatures[] = 'admin';
            }
        }

        $database = null;
        if ($this->hasFile('.env') || $this->hasFile('.env.local')) {
            $envContent = $this->readEnvironmentContent();
            if ($envContent !== null) {
                if (strpos($envContent, 'DB_HOST') !== false) {
                    if (strpos($envContent, 'mysql') !== false || strpos($envContent, 'mysqli') !== false) {
                        $database = 'mysql';
                    } elseif (strpos($envContent, 'pgsql') !== false || strpos($envContent, 'postgres') !== false) {
                        $database = 'postgresql';
                    } elseif (strpos($envContent, 'sqlite') !== false) {
                        $database = 'sqlite';
                    }
                }
            }
        }

        return [
            'name' => basename($this->projectPath),
            'type' => 'vanilla-php',
            'language' => 'php',
            'framework' => 'vanilla',
            'database' => $database,
            'version' => phpversion(),
            'hasDocker' => $this->hasFile('Dockerfile') || $this->hasFile('docker-compose.yml'),
            'hasGit' => $this->hasFile('.git'),
            'environmentFiles' => array_values(array_unique(array_map('basename', $this->findConfigFiles(['.env', '.env.local', '.env.production'])))),
            'configFiles' => $this->findConfigFiles(['composer.json', 'php.ini', 'web.config', '.htaccess']),
            'detectedFeatures' => array_unique($detectedFeatures),
            'phpFilesCount' => count($phpFiles),
        ];
    }

    public function checkHealth(): array
    {
        $issues = [];
        $warnings = [];

        if (!$this->isReadableFile($this->projectPath . '/.env') && !$this->isReadableFile($this->projectPath . '/.env.local')) {
            $warnings[] = 'No .env file found - may use default configuration';
        }

        $htaccess = $this->projectPath . '/.htaccess';
        if (file_exists($htaccess) && !is_readable($htaccess)) {
            $issues[] = '.htaccess file exists but not readable';
        }

        $uploadsDir = $this->projectPath . '/uploads';
        if (!is_dir($uploadsDir)) {
            $warnings[] = 'uploads directory missing';
        } elseif (!is_writable($uploadsDir)) {
            $issues[] = 'uploads directory not writable';
        }

        $phpCheck = ini_get('allow_url_fopen');
        if (!filter_var($phpCheck, FILTER_VALIDATE_BOOLEAN)) {
            $warnings[] = 'allow_url_fopen may be disabled';
        }

        return [
            'healthy' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    public function generateDemoData(array $options = []): array
    {
        return [
            'success' => false,
            'message' => 'Demo data generation not supported for vanilla PHP projects',
            'generated' => 0,
        ];
    }

    public function setProject(Project $project): void
    {
        $this->project = $project;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    private function isReadableFile(string $path): bool
    {
        return is_file($path) && is_readable($path);
    }

    private function readEnvironmentContent(): ?string
    {
        foreach (['.env','.env.local'] as $filename) {
            $path = $this->projectPath . DIRECTORY_SEPARATOR . $filename;
            if (!$this->isReadableFile($path)) continue;
            $realPath = realpath($path);
            if ($realPath === false || !str_starts_with($realPath, $this->projectPath . DIRECTORY_SEPARATOR)) continue;
            $content = @file_get_contents($realPath);
            if (is_string($content)) return $content;
        }
        return null;
    }
}
