<?php
declare(strict_types=1);

namespace DevPilot\Adapters;

use OutOfBoundsException;

/**
 * Versioned adapter contract for project-specific operations.
 *
 * Adapters must implement this interface to enable DevPilot to interact
 * with specific project types. Each adapter handles:
 * - Technology-specific discovery
 * - Safe metadata extraction
 * - Project health checks
 * - Demo data generation (requires explicit authorization)
 */
interface AdapterContract
{
    /**
     * Get the adapter version.
     */
    public static function getVersion(): string;

    /**
     * Get supported technology stack.
     */
    public static function getSupportedStack(): array;

    /**
     * Validate that this adapter can handle the given path.
     */
    public static function supports(string $path): bool;

    /**
     * Initialize the adapter for a specific project.
     *
     * @param string $projectPath Absolute path to the project root
     * @param array $config Optional configuration for this adapter instance
     */
    public function __construct(string $projectPath, array $config = []);

    /**
     * Discover project metadata without executing any code.
     *
     * Returns safe, read-only information about the project.
     * Does NOT execute build scripts, migrations, or any project code.
     *
     * @return array{
     *   name: string,
     *   type: string,
     *   language: string,
     *   framework: string,
     *   database: string|null,
     *   version: string|null,
     *   hasDocker: bool,
     *   hasGit: bool,
     *   environmentFiles: string[],
     *   configFiles: string[],
     *   detectedFeatures: string[]
     * }
     */
    public function discover(): array;

    /**
     * Run health/diagnostics checks on the project.
     *
     * @return array{healthy: bool, issues: string[], warnings: string[]}
     */
    public function checkHealth(): array;

    /**
     * List available commands for this project type.
     *
     * @return array{
     *   build: bool,
     *   test: bool,
     *   serve: bool,
     *   lint: bool,
     *   generateDemoData: bool
     * }
     */
    public function getAvailableCommands(): array;

    /**
     * Execute a demo data generation command.
     *
     * REQUIRES EXPLICIT AUTHORIZATION. This method MUST NOT be called
     * without the caller verifying user authorization.
     *
     * @param array $options Generation options
     * @return array{success: bool, message: string, generated: int}
     */
    public function generateDemoData(array $options = []): array;

    /**
     * Get the adapter configuration.
     */
    public function getConfig(): array;

    /**
     * Update the adapter configuration.
     */
    public function setConfig(array $config): void;
}