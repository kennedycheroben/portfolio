<?php
declare(strict_types=1);

namespace DevPilot;

use OutOfBoundsException;
use RuntimeException;

final class Registry
{
    private array $projects = [];
    private int $nextId = 1;
    private string $filePath;
    private static ?Registry $instance = null;

    public function __construct(string $filePath)
    {
        $this->filePath = dirname($filePath) . '/projects.json';
        $this->migrateIfNecessary();
        $this->load();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(Config::getRegistryPath());
        }
        return self::$instance;
    }

    private function migrateIfNecessary(): void
    {
        $devPilotRegistry = Config::getRoot() . '/registry/projects.json';
        $rootRegistry = dirname(dirname(Config::getRoot())) . '/portfolio/registry/projects.json';

        if (!is_readable($devPilotRegistry) && is_readable($rootRegistry)) {
            $content = file_get_contents($rootRegistry);
            if ($content !== false) {
                $dir = dirname($devPilotRegistry);
                if (!is_dir($dir)) {
                    mkdir($dir, 0700, true);
                }
                if (file_put_contents($devPilotRegistry, $content) !== false) {
                    chmod($devPilotRegistry, 0600);
                }
            }
        }
    }

    private function load(): void
    {
        if (!is_readable($this->filePath)) {
            return;
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new RuntimeException('Registry contains corrupted JSON data');
        }

        $this->projects = [];
        foreach ($data['projects'] ?? [] as $projectData) {
            if (!isset($projectData['id'])) {
                continue;
            }
            $project = Project::fromArray($projectData);
            $this->projects[$project->id] = $project;
        }

        $ids = array_keys($this->projects);
        $this->nextId = empty($ids) ? 1 : (max($ids) + 1);
    }

    private function save(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true)) {
                throw new RuntimeException('Failed to create registry directory');
            }
        }

        $tempFile = $this->filePath . '.tmp';

        $data = [
            'exported_at' => date('c'),
            'version' => '1.0',
            'count' => count($this->projects),
            'projects' => array_values($this->projects),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode registry data as JSON');
        }

        $handle = fopen($tempFile, 'w');
        if ($handle === false) {
            throw new RuntimeException('Failed to create temporary registry file');
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            unlink($tempFile);
            throw new RuntimeException('Failed to acquire exclusive lock on registry');
        }

        $written = fwrite($handle, $json);
        if ($written === false || $written !== strlen($json)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            unlink($tempFile);
            throw new RuntimeException('Failed to write complete registry content');
        }

        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        chmod($tempFile, 0600);

        if (!rename($tempFile, $this->filePath)) {
            unlink($tempFile);
            throw new RuntimeException('Failed to atomically replace registry file');
        }
    }

    public function add(Project $project): int
    {
        $existing = $this->findByPath($project->path);
        if ($existing !== null) {
            return $existing->id;
        }

        $project->id = $this->nextId++;
        $project->createdAt = date('c');
        $project->updatedAt = date('c');

        $this->projects[$project->id] = $project;
        $this->save();
        return $project->id;
    }

    public function update(Project $project): bool
    {
        if (!isset($this->projects[$project->id])) {
            return false;
        }

        $project->updatedAt = date('c');
        $this->projects[$project->id] = $project;
        $this->save();
        return true;
    }

    public function remove(int $id): bool
    {
        if (!isset($this->projects[$id])) {
            return false;
        }

        unset($this->projects[$id]);
        $this->save();
        return true;
    }

    public function get(int $id): ?Project
    {
        return $this->projects[$id] ?? null;
    }

    public function findByPath(string $path): ?Project
    {
        foreach ($this->projects as $project) {
            if ($project->path === $path) {
                return $project;
            }
        }
        return null;
    }

    public function findByName(string $name): ?Project
    {
        foreach ($this->projects as $project) {
            if (strtolower($project->name) === strtolower($name)) {
                return $project;
            }
        }
        return null;
    }

    /**
     * @return Project[]
     */
    public function list(
        ?string $environment = null,
        ?string $status = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $projects = array_values($this->projects);

        if ($environment !== null) {
            $projects = array_filter($projects, fn($p) => $p->environment === $environment);
        }

        if ($status !== null) {
            $projects = array_filter($projects, fn($p) => $p->status === $status);
        }

        usort($projects, fn($a, $b) => strcmp($a->name, $b->name));

        return array_slice($projects, $offset, $limit);
    }

    /**
     * @return int[]
     */
    public function getIds(): array
    {
        $ids = array_keys($this->projects);
        sort($ids);
        return $ids;
    }

    public function count(): int
    {
        return count($this->projects);
    }

    public function countByStatus(string $status): int
    {
        return count(array_filter($this->projects, fn($p) => $p->status === $status));
    }

    public function countByEnvironment(string $environment): int
    {
        return count(array_filter($this->projects, fn($p) => $p->environment === $environment));
    }

    public function exportAll(): string
    {
        $export = [
            'exported_at' => date('c'),
            'version' => '1.0',
            'count' => count($this->projects),
            'projects' => array_values($this->projects),
        ];

        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function import(string $json): int
    {
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['projects'])) {
            throw new RuntimeException('Invalid import format');
        }

        $count = 0;
        foreach ($data['projects'] as $row) {
            if (!isset($row['name'], $row['path'])) {
                continue;
            }

            $project = Project::fromArray($row);
            $this->projects[$project->id] = $project;
            if ($project->id >= $this->nextId) {
                $this->nextId = $project->id + 1;
            }
            $count++;
        }

        $this->save();
        return $count;
    }

    public function getAll(): array
    {
        return $this->projects;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}