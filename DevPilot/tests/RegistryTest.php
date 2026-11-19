<?php
declare(strict_types=1);

namespace DevPilot\Tests;

use DevPilot\Project;
use DevPilot\Registry;

final class RegistryTest extends TestCase
{
    private string $testDbPath;
    private Registry $registry;

    protected function setUp(): void
    {
        $this->testDbPath = self::$testRoot . '/registry.json';
        $registryPath = dirname($this->testDbPath) . '/projects.json';

        $this->registry = new Registry($registryPath);
    }

    public function testAddProject(): void
    {
        $project = new Project(
            id: 0,
            name: 'Test Project',
            path: self::$testRoot . '/test-project',
            language: 'php',
            framework: 'laravel',
            database: 'mysql'
        );

        $id = $this->registry->add($project);

        $this->assertGreaterThan(0, $id);
        $this->assertEquals('Test Project', $project->name);
    }

    public function testGetProject(): void
    {
        $project = new Project(
            id: 0,
            name: 'Get Test',
            path: self::$testRoot . '/get-test-path'
        );

        $id = $this->registry->add($project);
        $retrieved = $this->registry->get($id);

        $this->assertNotNull($retrieved);
        $this->assertEquals('Get Test', $retrieved->name);
        $this->assertEquals($id, $retrieved->id);
    }

    public function testGetNonexistentProject(): void
    {
        $result = $this->registry->get(99999);
        $this->assertNull($result);
    }

    public function testFindByPath(): void
    {
        $project = new Project(
            id: 0,
            name: 'Path Test',
            path: '/unique/test/path/12345'
        );

        $id = $this->registry->add($project);
        $found = $this->registry->findByPath('/unique/test/path/12345');

        $this->assertNotNull($found);
        $this->assertEquals('Path Test', $found->name);
    }

    public function testFindByNonexistentPath(): void
    {
        $result = $this->registry->findByPath('/nonexistent/path');
        $this->assertNull($result);
    }

    public function testUpdateProject(): void
    {
        $project = new Project(
            id: 0,
            name: 'Update Test',
            path: self::$testRoot . '/update-test',
            language: 'php'
        );

        $id = $this->registry->add($project);
        $project->language = 'javascript';
        $project->framework = 'vue';

        $result = $this->registry->update($project);

        $this->assertTrue($result);
        $updated = $this->registry->get($id);
        $this->assertEquals('javascript', $updated->language);
    }

    public function testRemoveProject(): void
    {
        $project = new Project(
            id: 0,
            name: 'Remove Test',
            path: self::$testRoot . '/remove-test'
        );

        $id = $this->registry->add($project);
        $this->assertNotNull($this->registry->get($id));

        $result = $this->registry->remove($id);

        $this->assertTrue($result);
        $this->assertNull($this->registry->get($id));
    }

    public function testListProjects(): void
    {
        $project1 = new Project(
            id: 0,
            name: 'A-Zebra Project',
            path: self::$testRoot . '/zebra'
        );

        $project2 = new Project(
            id: 0,
            name: 'A-Apple Project',
            path: self::$testRoot . '/apple'
        );

        $this->registry->add($project1);
        $this->registry->add($project2);

        $projects = $this->registry->list();

        $this->assertCount(2, $projects);
        $this->assertEquals('A-Apple Project', $projects[0]->name);
        $this->assertEquals('A-Zebra Project', $projects[1]->name);
    }

    public function testCountProjects(): void
    {
        $initialCount = $this->registry->count();

        $project1 = new Project(id: 0, name: 'Count Test 1', path: self::$testRoot . '/count1');
        $project2 = new Project(id: 0, name: 'Count Test 2', path: self::$testRoot . '/count2');

        $this->registry->add($project1);
        $this->registry->add($project2);

        $this->assertEquals($initialCount + 2, $this->registry->count());
    }

    public function testExportImport(): void
    {
        $project = new Project(
            id: 0,
            name: 'Export Test',
            path: self::$testRoot . '/export-test'
        );

        $this->registry->add($project);

        $json = $this->registry->exportAll();
        $data = json_decode($json, true);

        $this->assertArrayHasKey('projects', $data);
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('version', $data);
    }

    public function testDuplicatePathPrevention(): void
    {
        $project1 = new Project(
            id: 0,
            name: 'First',
            path: self::$testRoot . '/duplicate'
        );

        $project2 = new Project(
            id: 0,
            name: 'Duplicate',
            path: self::$testRoot . '/duplicate'
        );

        $id1 = $this->registry->add($project1);
        $id2 = $this->registry->add($project2);

        $this->assertEquals($id1, $id2);
        $this->assertEquals(1, $this->registry->count());
    }
}