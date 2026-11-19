<?php
declare(strict_types=1);

namespace DevPilot;

use OutOfBoundsException;

final class Project
{
    public int $id;
    public string $name;
    public string $path;
    public string $language;
    public string $framework;
    public ?string $database;
    public string $environment;
    public string $status;
    public string $adapterStatus;
    public ?string $hosting;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(
        int $id,
        string $name,
        string $path,
        string $language = '',
        string $framework = '',
        ?string $database = null,
        string $environment = 'development',
        string $status = 'active',
        string $adapterStatus = 'pending',
        ?string $hosting = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->path = $path;
        $this->language = $language;
        $this->framework = $framework;
        $this->database = $database;
        $this->environment = $environment;
        $this->status = $status;
        $this->adapterStatus = $adapterStatus;
        $this->hosting = $hosting;
        $this->createdAt = date('c');
        $this->updatedAt = date('c');
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? 0,
            name: $data['name'] ?? '',
            path: $data['path'] ?? '',
            language: $data['language'] ?? '',
            framework: $data['framework'] ?? '',
            database: $data['database'] ?? null,
            environment: $data['environment'] ?? 'development',
            status: $data['status'] ?? 'active',
            adapterStatus: $data['adapter_status'] ?? 'pending',
            hosting: $data['hosting'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'path' => $this->path,
            'language' => $this->language,
            'framework' => $this->framework,
            'database' => $this->database,
            'environment' => $this->environment,
            'status' => $this->status,
            'adapter_status' => $this->adapterStatus,
            'hosting' => $this->hosting,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function validate(): bool
    {
        return !empty($this->name) && !empty($this->path);
    }

    public function pathExists(): bool
    {
        return is_dir($this->path);
    }
}