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
            'php' => '>=5.7',
        ];
    }

    public static function supports(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $files = glob($path . '/*.php');
        if (empty($files)) {
            return false;
        }

        $hasNoFramework = !self::hasFrameworkFiles($path);

        return $hasNoFramework || self::hasVanillaPhpStructure($path);
    }

    private static function hasFrameworkFiles(string $path): bool
    {
        return file_exists($path . '/artisan')
            || file_exists($path . '/bootstrap/app.php')
            || file_exists($path . '/composer.json');
    }

    private static function hasVanillaPhpStructure(string $path): bool
    {
        $phpFiles = glob($path . '/*.php');
        if (empty($phpFiles)) {
            return false;
        }

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            if (strpos($content, '<?php') !== false && !strpos($content, 'namespace')) {
                return true;
            }
        }

        return false;
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
        $phpFiles = glob($this->projectPath . '/*.php') ?: [];
        $composerPath = $this->projectPath . '/composer.json';

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
            $envContent = $this->readSafeFile($this->projectPath . '/.env');
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
            'environmentFiles' => $this->findConfigFiles(['.env', '.env.local', '.env.production']),
            'configFiles' => $this->findConfigFiles(['composer.json', 'php.ini', 'web.config', '.htaccess']),
            'detectedFeatures' => array_unique($detectedFeatures),
            'phpFilesCount' => count($phpFiles),
        ];
    }

    public function checkHealth(): array
    {
        $issues = [];
        $warnings = [];

        if (!is_readable($this->projectPath . '/.env') && !is_readable($this->projectPath . '/.env.local')) {
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

        $phpCheck = @ini_get('allow_url_fopen');
        if ($phpCheck !== '1' && $phpCheck !== '1') {
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
}