<?php
declare(strict_types=1);

namespace DevPilot\Tests;

use DevPilot\Adapters\AdapterContract;
use DevPilot\Adapters\Php\VanillaPhpAdapter;

final class AdapterCompatibilityTest extends TestCase
{
    public function testVanillaPhpAdapterImplementsInterface(): void
    {
        $adapterClass = VanillaPhpAdapter::class;

        $this->assertTrue(class_exists($adapterClass), 'VanillaPhpAdapter class exists');

        $reflection = new \ReflectionClass($adapterClass);
        $interface = new \ReflectionClass(AdapterContract::class);

        foreach ($interface->getMethods() as $interfaceMethod) {
            $methodName = $interfaceMethod->getName();
            if ($methodName === '__construct') continue;

            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "Adapter implements interface method: {$methodName}"
            );

            $adapterMethod = $reflection->getMethod($methodName);
            $this->assertEquals(
                $interfaceMethod->isPublic(),
                $adapterMethod->isPublic(),
                "Method {$methodName} has correct visibility"
            );
        }
    }

    public function testVanillaPhpAdapterInstantiation(): void
    {
        $testPath = sys_get_temp_dir() . '/devpilot-adapter-test';
        if (!is_dir($testPath)) {
            mkdir($testPath, 0755, true);
        }
        file_put_contents($testPath . '/index.php', '<?php echo "test"; ?>');

        $adapter = new VanillaPhpAdapter($testPath);

        $this->assertInstanceOf(VanillaPhpAdapter::class, $adapter);
        $this->assertSame($testPath, $adapter->getProjectPath());
    }

    public function testGetAvailableCommandsIsInstanceMethod(): void
    {
        $testPath = sys_get_temp_dir() . '/devpilot-adapter-test';
        file_put_contents($testPath . '/index.php', '<?php echo "test"; ?>');

        $adapter = new VanillaPhpAdapter($testPath);

        $commands = $adapter->getAvailableCommands();

        $this->assertIsArray($commands);
        $this->assertArrayHasKey('build', $commands);
        $this->assertArrayHasKey('test', $commands);
        $this->assertArrayHasKey('serve', $commands);
        $this->assertArrayHasKey('lint', $commands);
        $this->assertArrayHasKey('generateDemoData', $commands);
    }

    public function testGetSupportedStackReturnsValidArray(): void
    {
        $stack = VanillaPhpAdapter::getSupportedStack();

        $this->assertIsArray($stack);
        $this->assertArrayHasKey('php', $stack);
        $this->assertNotEmpty($stack['php']);

        $keys = array_keys($stack);
        $this->assertCount(1, $keys, 'Should have exactly one PHP key (no duplicates)');

        @unlink(sys_get_temp_dir() . '/devpilot-adapter-test/index.php');
        @rmdir(sys_get_temp_dir() . '/devpilot-adapter-test');
    }

    public function testAdapterSupportsValidPath(): void
    {
        $testPath = sys_get_temp_dir() . '/devpilot-adapter-test';
        file_put_contents($testPath . '/index.php', '<?php echo "test"; ?>');

        $supports = VanillaPhpAdapter::supports($testPath);

        $this->assertTrue($supports);

        @unlink($testPath . '/index.php');
        @rmdir($testPath);
    }

    public function testAdapterSupportsInvalidPath(): void
    {
        $testPath = sys_get_temp_dir() . '/devpilot-adapter-test-nonexistent';

        $supports = VanillaPhpAdapter::supports($testPath);

        $this->assertFalse($supports);
    }

    public function testAdapterInterfaceCompatibility(): void
    {
        $testPath = sys_get_temp_dir() . '/devpilot-adapter-test';
        file_put_contents($testPath . '/index.php', '<?php echo "test"; ?>');

        $adapter = new VanillaPhpAdapter($testPath);

        // Test all interface methods can be called
        $this->assertIsString($adapter::getVersion());
        $this->assertIsArray($adapter::getSupportedStack());
        $this->assertIsBool($adapter::supports($testPath));

        $discovered = $adapter->discover();
        $this->assertIsArray($discovered);
        $this->assertArrayHasKey('name', $discovered);
        $this->assertArrayHasKey('type', $discovered);

        $health = $adapter->checkHealth();
        $this->assertIsArray($health);
        $this->assertArrayHasKey('healthy', $health);

        $commands = $adapter->getAvailableCommands();
        $this->assertIsArray($commands);

        $demoData = $adapter->generateDemoData([]);
        $this->assertIsArray($demoData);

        @unlink($testPath . '/index.php');
        @rmdir($testPath);
    }
}