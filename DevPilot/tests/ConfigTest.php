<?php
declare(strict_types=1);

namespace DevPilot\Tests;

use DevPilot\Config;

final class ConfigTest extends TestCase
{
    public function testGetRoot(): void
    {
        $root = Config::getRoot();

        $this->assertStringContainsString('DevPilot', $root);
        $this->assertTrue(is_dir($root));
    }

    public function testGetRegistryPath(): void
    {
        $path = Config::getRegistryPath();

        $this->assertStringContainsString('registry', $path);
        $this->assertStringContainsString('projects.json', $path);
    }

    public function testEnsureDirectories(): void
    {
        Config::ensureDirectories();

        $root = Config::getRoot();

        $this->assertTrue(is_dir($root . '/adapters'));
        $this->assertTrue(is_dir($root . '/adapters/php'));
        $this->assertTrue(is_dir($root . '/diagnostics'));
        $this->assertTrue(is_dir($root . '/tests'));
    }

    public function testValidatePathValidPath(): void
    {
        $result = Config::validatePath('/opt/lampp/htdocs/portfolio');

        $this->assertTrue(is_dir($result));
    }

    public function testValidatePathEmptyThrowsException(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        Config::validatePath('');
    }

    public function testValidatePathNonexistentThrowsException(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        Config::validatePath('/nonexistent/path/');
    }

    public function testValidatePathTraversal(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        Config::validatePath('/opt/lampp/htdocs/portfolio/../../../etc/passwd');
    }

    public function testSetGet(): void
    {
        Config::set('test_key', 'test_value');
        $value = Config::get('test_key', 'default');

        $this->assertEquals('test_value', $value);
    }
}