<?php
declare(strict_types=1);

namespace DevPilot\Tests;

use DevPilot\Adapters\AdapterContract;
use DevPilot\Adapters\Php\VanillaPhpAdapter;

final class AdapterCompatibilityTest
{
    private string $testPath;

    public function setUp(): void
    {
        $this->testPath = sys_get_temp_dir() . '/devpilot-adapter-test-' . uniqid();
        if (!is_dir($this->testPath)) {
            mkdir($this->testPath, 0755, true);
        }
        file_put_contents($this->testPath . '/index.php', '<?php echo "test"; ?>');
    }

    public function tearDown(): void
    {
        if (is_dir($this->testPath)) {
            $this->removeDirectory($this->testPath);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }
        rmdir($dir);
    }

    public function testVanillaPhpAdapterImplementsInterface(): void
    {
        $adapterClass = VanillaPhpAdapter::class;

        if (!class_exists($adapterClass)) {
            throw new \AssertionError('VanillaPhpAdapter class does not exist');
        }

        $reflection = new \ReflectionClass($adapterClass);
        $interface = new \ReflectionClass(AdapterContract::class);

        foreach ($interface->getMethods() as $interfaceMethod) {
            $methodName = $interfaceMethod->getName();
            if ($methodName === '__construct') continue;

            if (!$reflection->hasMethod($methodName)) {
                throw new \AssertionError("Adapter implements interface method: {$methodName}");
            }

            $adapterMethod = $reflection->getMethod($methodName);
            if ($interfaceMethod->isPublic() !== $adapterMethod->isPublic()) {
                throw new \AssertionError("Method {$methodName} has correct visibility");
            }
        }
    }

    public function testVanillaPhpAdapterInstantiation(): void
    {
        $adapter = new VanillaPhpAdapter($this->testPath);

        if (!($adapter instanceof VanillaPhpAdapter)) {
            throw new \AssertionError("Adapter is not instance of VanillaPhpAdapter");
        }
        if ($adapter->getProjectPath() !== $this->testPath) {
            throw new \AssertionError("Adapter project path mismatch");
        }
    }

    public function testGetAvailableCommandsIsInstanceMethod(): void
    {
        $adapter = new VanillaPhpAdapter($this->testPath);

        $commands = $adapter->getAvailableCommands();

        if (!is_array($commands)) {
            throw new \AssertionError("Commands should be array");
        }
        foreach (['build', 'test', 'serve', 'lint', 'generateDemoData'] as $key) {
            if (!array_key_exists($key, $commands)) {
                throw new \AssertionError("Missing command key: {$key}");
            }
        }
    }

    public function testGetSupportedStackReturnsValidArray(): void
    {
        $stack = VanillaPhpAdapter::getSupportedStack();

        if (!is_array($stack)) {
            throw new \AssertionError("Supported stack should be array");
        }
        if (!array_key_exists('php', $stack)) {
            throw new \AssertionError("Missing php key in supported stack");
        }
        if (empty($stack['php'])) {
            throw new \AssertionError("PHP version constraint should not be empty");
        }

        $keys = array_keys($stack);
        if (count($keys) !== 1) {
            throw new \AssertionError("Should have exactly one PHP key (no duplicates)");
        }
    }

    public function testAdapterSupportsValidPath(): void
    {
        $supports = VanillaPhpAdapter::supports($this->testPath);

        if (!$supports) {
            throw new \AssertionError("Adapter should support valid path");
        }
    }

    public function testAdapterSupportsInvalidPath(): void
    {
        $testPath = sys_get_temp_dir() . '/devpilot-adapter-test-nonexistent';

        $supports = VanillaPhpAdapter::supports($testPath);

        if ($supports) {
            throw new \AssertionError("Adapter should not support invalid path");
        }
    }

    public function testAdapterInterfaceCompatibility(): void
    {
        $adapter = new VanillaPhpAdapter($this->testPath);

        if (!is_string($adapter::getVersion())) {
            throw new \AssertionError("getVersion should return string");
        }
        if (!is_array($adapter::getSupportedStack())) {
            throw new \AssertionError("getSupportedStack should return array");
        }
        if (!is_bool($adapter::supports($this->testPath))) {
            throw new \AssertionError("supports should return bool");
        }

        $discovered = $adapter->discover();
        if (!is_array($discovered)) {
            throw new \AssertionError("discover should return array");
        }
        if (!array_key_exists('name', $discovered)) {
            throw new \AssertionError("discover missing name key");
        }
        if (!array_key_exists('type', $discovered)) {
            throw new \AssertionError("discover missing type key");
        }

        $health = $adapter->checkHealth();
        if (!is_array($health)) {
            throw new \AssertionError("checkHealth should return array");
        }
        if (!array_key_exists('healthy', $health)) {
            throw new \AssertionError("health missing 'healthy' key");
        }

        $commands = $adapter->getAvailableCommands();
        if (!is_array($commands)) {
            throw new \AssertionError("getAvailableCommands should return array");
        }

        $demoData = $adapter->generateDemoData([]);
        if (!is_array($demoData)) {
            throw new \AssertionError("generateDemoData should return array");
        }
    }
}