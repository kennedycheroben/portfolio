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
}