<?php
declare(strict_types=1);

namespace DevPilot\Tests;

/**
 * Base test case class without PHPUnit dependency.
 */
abstract class TestCase
{
    protected static string $testRoot;

    public static function setUpBeforeClass(): void
    {
        self::$testRoot = sys_get_temp_dir() . '/devpilot-test-' . uniqid();
        if (!is_dir(self::$testRoot)) {
            mkdir(self::$testRoot, 0755, true);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_dir(self::$testRoot)) {
            self::removeDirectory(self::$testRoot);
        }
    }

    private static function removeDirectory(string $dir): void
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