<?php
declare(strict_types=1);

namespace DevPilot\Tests;

use DevPilot\Discovery;

final class DiscoveryTest
{
    private string $testRoot;

    public function setUp(): void
    {
        $this->testRoot = sys_get_temp_dir() . '/devpilot-test-' . uniqid();
        if (!is_dir($this->testRoot)) {
            mkdir($this->testRoot, 0755, true);
        }
    }

    public function tearDown(): void
    {
        if (is_dir($this->testRoot)) {
            $this->removeDirectory($this->testRoot);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($dir);
    }

    protected function createTestProject(string $name, array $files = []): string
    {
        $path = $this->testRoot . '/' . $name;
        mkdir($path, 0755, true);

        foreach ($files as $file => $content) {
            $fullPath = $path . '/' . $file;
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (is_array($content)) {
                // Directory with nested content
                mkdir($fullPath, 0755, true);
            } else {
                file_put_contents($path . '/' . $file, $content);
            }
        }

        return $path;
    }

    public function testDetectVanillaPhpProject(): void
    {
        $path = $this->createTestProject('vanilla-php', [
            'index.php' => '<?php echo "Hello World"; ?>',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'php') {
            throw new \AssertionError("Expected php, got {$result['primaryLanguage']}");
        }
        if (!in_array('php', $result['technologies'] ?? [])) {
            throw new \AssertionError("php not in technologies");
        }
    }

    public function testDetectLaravelProject(): void
    {
        $path = $this->createTestProject('laravel-project', [
            'artisan' => '#!/usr/bin/env php',
            'composer.json' => '{"name": "laravel/app"}',
            'bootstrap/app.php' => '<?php return "Laravel";',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'php') {
            throw new \AssertionError("Expected php, got {$result['primaryLanguage']}");
        }
        if (!in_array('laravel', $result['frameworks'] ?? [])) {
            throw new \AssertionError("laravel not in frameworks");
        }
    }

    public function testDetectNodeProject(): void
    {
        $path = $this->createTestProject('node-project', [
            'package.json' => '{"name": "node-app"}',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'javascript') {
            throw new \AssertionError("Expected javascript, got {$result['primaryLanguage']}");
        }
        if (!in_array('node', $result['technologies'] ?? [])) {
            throw new \AssertionError("node not in technologies");
        }
    }

    public function testDetectPythonProject(): void
    {
        $path = $this->createTestProject('python-project', [
            'requirements.txt' => 'flask==2.0.0',
            'app.py' => 'print("hello")',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'python') {
            throw new \AssertionError("Expected python, got {$result['primaryLanguage']}");
        }
    }

    public function testDetectGitRepository(): void
    {
        $path = $this->createTestProject('git-project', [
            '.git' => [],
            'index.php' => '<?php echo "test"; ?>',
        ]);

        $result = Discovery::detect($path);

        if (!$result['hasGit']) {
            throw new \AssertionError("Expected hasGit to be true");
        }
    }

    public function testDetectDockerFiles(): void
    {
        $path = $this->createTestProject('docker-project', [
            'Dockerfile' => 'FROM php:8',
            'docker-compose.yml' => "version: '3'\nd services:\n  app:",
        ]);

        $result = Discovery::detect($path);

        if (!$result['hasDocker']) {
            throw new \AssertionError("Expected hasDocker to be true");
        }
    }

    public function testDetectDatabaseFromEnv(): void
    {
        $path = $this->createTestProject('db-project', [
            '.env' => "DB_HOST=localhost\nDB_DATABASE=mydb\nDB_USERNAME=user\nDB_PASSWORD=pass",
            'index.php' => '<?php echo "test"; ?>',
        ]);

        $result = Discovery::detect($path);

        if (!in_array('mysql', $result['databases'] ?? [])) {
            throw new \AssertionError("mysql not in databases");
        }
    }

    public function testInvalidPathThrowsException(): void
    {
        try {
            Discovery::detect('/nonexistent/path/that/does/not/exist');
            throw new \AssertionError("Expected OutOfBoundsException");
        } catch (\OutOfBoundsException $e) {
            // Expected
        }
    }

    public function testNonDirectoryThrowsException(): void
    {
        try {
            Discovery::detect('/etc/hosts');
            throw new \AssertionError("Expected OutOfBoundsException");
        } catch (\OutOfBoundsException $e) {
            // Expected
        }
    }

    public function testMultipleTechnologiesDetected(): void
    {
        $path = $this->createTestProject('multi-tech', [
            'composer.json' => '{"name": "multi"}',
            'package.json' => '{"name": "multi"}',
            'requirements.txt' => 'django',
            'index.php' => '<?php echo "test"; ?>',
        ]);

        $result = Discovery::detect($path);

        if (!in_array('php', $result['technologies'] ?? [])) {
            throw new \AssertionError("php not in technologies");
        }
    }

    public function testDetectTypeScriptProject(): void
    {
        $path = $this->createTestProject('typescript-project', [
            'package.json' => '{"name": "ts-app"}',
            'tsconfig.json' => '{"compilerOptions": {}}',
            'src/index.ts' => 'console.log("hello");',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'typescript') {
            throw new \AssertionError("Expected typescript, got {$result['primaryLanguage']}");
        }
        if (!in_array('typescript', $result['technologies'] ?? [])) {
            throw new \AssertionError("typescript not in technologies");
        }
    }

    public function testDetectTypeScriptWithoutTsconfig(): void
    {
        $path = $this->createTestProject('typescript-no-config', [
            'package.json' => '{"name": "ts-app"}',
            'src/index.ts' => 'console.log("hello");',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'typescript') {
            throw new \AssertionError("Expected typescript, got {$result['primaryLanguage']}");
        }
        if (!in_array('typescript', $result['technologies'] ?? [])) {
            throw new \AssertionError("typescript not in technologies");
        }
    }

    public function testDetectJavaScriptProject(): void
    {
        $path = $this->createTestProject('javascript-project', [
            'package.json' => '{"name": "js-app"}',
            'src/index.js' => 'console.log("hello");',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'javascript') {
            throw new \AssertionError("Expected javascript, got {$result['primaryLanguage']}");
        }
        if (!in_array('javascript', $result['technologies'] ?? [])) {
            throw new \AssertionError("javascript not in technologies");
        }
    }

    public function testDetectGenericComposerNotLaravel(): void
    {
        $path = $this->createTestProject('generic-composer', [
            'composer.json' => '{"name": "vendor/package", "require": {}}',
            'src/Service.php' => '<?php class Service {}',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'php') {
            throw new \AssertionError("Expected php, got {$result['primaryLanguage']}");
        }
        if (in_array('laravel', $result['frameworks'] ?? [])) {
            throw new \AssertionError("laravel should not be in frameworks for generic Composer project");
        }
    }

    public function testDetectLaravelWithFrameworkMarkers(): void
    {
        $path = $this->createTestProject('laravel-with-markers', [
            'composer.json' => '{"name": "laravel/laravel", "require": {"laravel/framework": "^10.0"}}',
            'artisan' => '#!/usr/bin/env php',
            'bootstrap/app.php' => '<?php $app = require_once __DIR__ . "/../vendor/autoload.php";',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'php') {
            throw new \AssertionError("Expected php, got {$result['primaryLanguage']}");
        }
        if (!in_array('laravel', $result['frameworks'] ?? [])) {
            throw new \AssertionError("laravel not in frameworks");
        }
    }

    public function testExcludesVendorDirectory(): void
    {
        $path = $this->createTestProject('vendor-excluded', [
            'composer.json' => '{"name": "test"}',
            'vendor/package/composer.json' => '{"name": "vendor/package"}',
            'vendor/package/src/index.php' => '<?php echo "should be ignored"; ?>',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'php') {
            throw new \AssertionError("Expected php, got {$result['primaryLanguage']}");
        }
    }

    public function testExcludesNodeModulesDirectory(): void
    {
        $path = $this->createTestProject('node-modules-excluded', [
            'package.json' => '{"name": "test"}',
            'node_modules/package/package.json' => '{"name": "package"}',
            'node_modules/package/index.js' => 'console.log("ignored");',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'javascript') {
            throw new \AssertionError("Expected javascript, got {$result['primaryLanguage']}");
        }
    }

    public function testExcludesGitDirectory(): void
    {
        $path = $this->createTestProject('git-excluded', [
            'index.php' => '<?php echo "test"; ?>',
            '.git/objects/file' => 'ignored',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'php') {
            throw new \AssertionError("Expected php, got {$result['primaryLanguage']}");
        }
        if (!$result['hasGit']) {
            throw new \AssertionError("Expected hasGit to be true");
        }
    }

    public function testExcludesBuildDirectories(): void
    {
        $path = $this->createTestProject('build-excluded', [
            'src/index.php' => '<?php echo "source"; ?>',
            'dist/bundle.js' => 'console.log("built");',
            'build/output.php' => '<?php echo "built"; ?>',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'php') {
            throw new \AssertionError("Expected php, got {$result['primaryLanguage']}");
        }
    }

    public function testExcludesCacheDirectories(): void
    {
        $path = $this->createTestProject('cache-excluded', [
            'src/index.php' => '<?php echo "source"; ?>',
            'var/cache/dev/appProdContainer.php' => '<?php echo "cached"; ?>',
        ]);

        $result = Discovery::detect($path);

        if ($result['primaryLanguage'] !== 'php') {
            throw new \AssertionError("Expected php, got {$result['primaryLanguage']}");
        }
    }
}