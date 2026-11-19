<?php
declare(strict_types=1);

namespace DevPilot\Tests;

use DevPilot\Discovery;

final class DiscoveryTest extends TestCase
{
    public function testDetectVanillaPhpProject(): void
    {
        $path = $this->createTestProject('vanilla-php', [
            'index.php' => '<?php echo "Hello World"; ?>',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('php', $result['primaryLanguage']);
        $this->assertContains('php', $result['technologies']);
    }

    public function testDetectLaravelProject(): void
    {
        $path = $this->createTestProject('laravel-project', [
            'artisan' => '#!/usr/bin/env php',
            'composer.json' => '{"name": "laravel/app"}',
            'bootstrap/app.php' => '<?php return "Laravel";',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('php', $result['primaryLanguage']);
        $this->assertContains('laravel', $result['frameworks']);
    }

    public function testDetectNodeProject(): void
    {
        $path = $this->createTestProject('node-project', [
            'package.json' => '{"name": "node-app"}',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('javascript', $result['primaryLanguage']);
        $this->assertContains('node', $result['technologies']);
    }

    public function testDetectPythonProject(): void
    {
        $path = $this->createTestProject('python-project', [
            'requirements.txt' => 'flask==2.0.0',
            'app.py' => 'print("hello")',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('python', $result['primaryLanguage']);
    }

    public function testDetectGitRepository(): void
    {
        $path = $this->createTestProject('git-project', [
            '.git' => [],
            'index.php' => '<?php echo "test"; ?>',
        ]);

        $result = Discovery::detect($path);

        $this->assertTrue($result['hasGit']);
    }

    public function testDetectDockerFiles(): void
    {
        $path = $this->createTestProject('docker-project', [
            'Dockerfile' => 'FROM php:8',
            'docker-compose.yml' => "version: '3'\nd services:\n  app:",
        ]);

        $result = Discovery::detect($path);

        $this->assertTrue($result['hasDocker']);
    }

    public function testDetectDatabaseFromEnv(): void
    {
        $path = $this->createTestProject('db-project', [
            '.env' => "DB_HOST=localhost\nDB_DATABASE=mydb\nDB_USERNAME=user\nDB_PASSWORD=pass",
            'index.php' => '<?php echo "test"; ?>',
        ]);

        $result = Discovery::detect($path);

        $this->assertContains('mysql', $result['databases'] ?? []);
    }

    public function testInvalidPathThrowsException(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        Discovery::detect('/nonexistent/path/that/does/not/exist');
    }

    public function testNonDirectoryThrowsException(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        Discovery::detect('/etc/hosts');
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

        $this->assertContains('php', $result['technologies']);
    }

    public function testDetectTypeScriptProject(): void
    {
        $path = $this->createTestProject('typescript-project', [
            'package.json' => '{"name": "ts-app"}',
            'tsconfig.json' => '{"compilerOptions": {}}',
            'src/index.ts' => 'console.log("hello");',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('typescript', $result['primaryLanguage']);
        $this->assertContains('typescript', $result['technologies']);
    }

    public function testDetectTypeScriptWithoutTsconfig(): void
    {
        $path = $this->createTestProject('typescript-no-config', [
            'package.json' => '{"name": "ts-app"}',
            'src/index.ts' => 'console.log("hello");',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('typescript', $result['primaryLanguage']);
        $this->assertContains('typescript', $result['technologies']);
    }

    public function testDetectJavaScriptProject(): void
    {
        $path = $this->createTestProject('javascript-project', [
            'package.json' => '{"name": "js-app"}',
            'src/index.js' => 'console.log("hello");',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('javascript', $result['primaryLanguage']);
        $this->assertContains('javascript', $result['technologies']);
    }

    public function testDetectGenericComposerNotLaravel(): void
    {
        $path = $this->createTestProject('generic-composer', [
            'composer.json' => '{"name": "vendor/package", "require": {}}',
            'src/Service.php' => '<?php class Service {}',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('php', $result['primaryLanguage']);
        $this->assertNotContains('laravel', $result['frameworks'] ?? []);
    }

    public function testDetectLaravelWithFrameworkMarkers(): void
    {
        $path = $this->createTestProject('laravel-with-markers', [
            'composer.json' => '{"name": "laravel/laravel", "require": {"laravel/framework": "^10.0"}}',
            'artisan' => '#!/usr/bin/env php',
            'bootstrap/app.php' => '<?php $app = require_once __DIR__ . "/../vendor/autoload.php";',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('php', $result['primaryLanguage']);
        $this->assertContains('laravel', $result['frameworks']);
    }

    public function testExcludesVendorDirectory(): void
    {
        $path = $this->createTestProject('vendor-excluded', [
            'composer.json' => '{"name": "test"}',
            'vendor/package/composer.json' => '{"name": "vendor/package"}',
            'vendor/package/src/index.php' => '<?php echo "should be ignored"; ?>',
        ]);

        $result = Discovery::detect($path);

        // Should not scan vendor directory
        $this->assertEquals('php', $result['primaryLanguage']);
    }

    public function testExcludesNodeModulesDirectory(): void
    {
        $path = $this->createTestProject('node-modules-excluded', [
            'package.json' => '{"name": "test"}',
            'node_modules/package/package.json' => '{"name": "package"}',
            'node_modules/package/index.js' => 'console.log("ignored");',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('javascript', $result['primaryLanguage']);
    }

    public function testExcludesGitDirectory(): void
    {
        $path = $this->createTestProject('git-excluded', [
            'index.php' => '<?php echo "test"; ?>',
            '.git/objects/file' => 'ignored',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('php', $result['primaryLanguage']);
        $this->assertTrue($result['hasGit']);
    }

    public function testExcludesBuildDirectories(): void
    {
        $path = $this->createTestProject('build-excluded', [
            'src/index.php' => '<?php echo "source"; ?>',
            'dist/bundle.js' => 'console.log("built");',
            'build/output.php' => '<?php echo "built"; ?>',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('php', $result['primaryLanguage']);
    }

    public function testExcludesCacheDirectories(): void
    {
        $path = $this->createTestProject('cache-excluded', [
            'src/index.php' => '<?php echo "source"; ?>',
            'var/cache/dev/appProdContainer.php' => '<?php echo "cached"; ?>',
        ]);

        $result = Discovery::detect($path);

        $this->assertEquals('php', $result['primaryLanguage']);
    }
}