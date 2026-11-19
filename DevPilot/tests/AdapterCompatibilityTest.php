<?php
declare(strict_types=1);

namespace DevPilot\Tests;

use DevPilot\AdapterFactory;

final class AdapterCompatibilityTest extends TestCase
{
    public function testVanillaPhpAdapterImplementsInterface(): void
    {
        $adapterClass = 'DevPilot\Adapters\Php\VanillaPhpAdapter';

        $this->assertTrue(class_exists($adapterClass), 'VanillaPhpAdapter class exists');

        $reflection = new \ReflectionClass($adapterClass);
        $interface = new \ReflectionClass(\DevPilot\AdapterContract::class);

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

        $adapterClass = 'DevPilot\Adapters\Php\VanillaPhpAdapter';
        $adapter = new $adapterClass($testPath);

        $this->assertInstanceOf($adapterClass::class, $adapter);
        $this->assertSame($testPath, $adapter->getProjectPath());
    }

    public function testGetAvailableCommandsIsInstanceMethod(): void
    {
        $testPath = sys_get_temp_dir() . '/devpilot-adapter-test';
        file_put_contents($testPath . '/index.php', '<?php echo "test"; ?>');

        $adapterClass = 'DevPilot\Adapters\Php\VanillaPhpAdapter';
        $adapter = new $adapterClass($testPath);

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
        $adapterClass = 'DevPilot\Adapters\Php\VanillaPhpAdapter';
        $stack = $adapterClass::getSupportedStack();

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

        $adapterClass = 'DevPilot\Adapters\Php\VanillaPhpAdapter';
        $supports = $adapterClass::supports($testPath);

        $this->assertTrue($supports);

        @unlink($testPath . '/index.php');
        @rmdir($testPath);
    }
}