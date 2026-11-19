<?php
declare(strict_types=1);

namespace DevPilot\Adapters;

use DevPilot\OutOfBoundsException;
use DevPilot\Project;
use DevPilot\Registry;

/**
 * Base adapter implementation with common utilities.
 */
abstract class AbstractAdapter implements AdapterContract
{
    protected string $projectPath;
    protected array $config;
    protected ?Registry $registry = null;
    protected ?Project $project = null;

    public function __construct(string $projectPath, array $config = [])
    {
        $this->config = $config;
        $this->projectPath = $this->validatePath($projectPath);
    }

    protected function validatePath(string $path): string
    {
        if (empty($path)) {
            throw new OutOfBoundsException('Project path cannot be empty');
        }

        $realPath = realpath($path);
        if ($realPath === false) {
            throw new OutOfBoundsException("Project path does not exist: {$path}");
        }

        if (!is_dir($realPath)) {
            throw new OutOfBoundsException("Project path is not a directory: {$path}");
        }

        return $realPath;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getProjectPath(): string
    {
        return $this->projectPath;
    }

    protected function findConfigFiles(array $filenames): array
    {
        $found = [];
        foreach ($filenames as $filename) {
            $path = $this->findFile($this->projectPath, $filename);
            if ($path !== null) {
                $found[] = $path;
            }
        }
        return $found;
    }

    protected function findFile(string $path, string $filename): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }

        return null;
    }

    protected function hasFile(string $filename): bool
    {
        return $this->findFile($this->projectPath, $filename) !== null;
    }

    protected function readSafeFile(string $path): ?string
    {
        $validated = $this->validatePath(dirname($path));
        if (!str_starts_with(realpath($path) ?: $path, $validated)) {
            return null;
        }
        return file_get_contents($path) ?: null;
    }

    protected function parseJsonFile(string $path): ?array
    {
        $content = $this->readSafeFile($path);
        if ($content === null) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }
}