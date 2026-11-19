<?php
declare(strict_types=1);

namespace DevPilot\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use DevPilot\Config;
use DevPilot\Discovery;

abstract class TestCase extends BaseTestCase
{
    protected static string $testRoot;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testRoot = sys_get_temp_dir() . '/devpilot-test-' . uniqid();
        if (!is_dir(self::$testRoot)) {
            mkdir(self::$testRoot, 0755, true);
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        if (is_dir(self::$testRoot)) {
            self::removeDirectory(self::$testRoot);
        }
    }

    private static function removeDirectory(string $dir): void
    {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            is_dir($file) ? self::removeDirectory($file) : unlink($file);
        }
        rmdir($dir);
    }

    protected function createTestProject(string $name, array $files = []): string
    {
        $path = self::$testRoot . '/' . $name;
        mkdir($path, 0755, true);

        foreach ($files as $file => $content) {
            if (is_array($content)) {
                $dir = $path . '/' . $file;
                if (!is_dir(dirname($dir))) {
                    mkdir(dirname($dir), 0755, true);
                }
                file_put_contents($dir, is_string($content) ? $content : '');
            } else {
                file_put_contents($path . '/' . $file, $content);
            }
        }

        return $path;
    }
}