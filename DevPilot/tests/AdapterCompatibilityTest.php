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
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
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
        if ($adapter->getProjectPath() !== realpath($this->testPath)) {
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

    public function testVanillaDetectionRejectsFrameworksAndEmptyOrUnreadableCandidates(): void
    {
        $framework = $this->testPath . '/framework';
        mkdir($framework);
        file_put_contents($framework . '/index.php', '<?php echo "app";');
        file_put_contents($framework . '/composer.json', json_encode(['require'=>['laravel/framework'=>'^11.0']]));
        if (VanillaPhpAdapter::supports($framework)) throw new \AssertionError('Laravel project was classified as vanilla PHP.');

        $empty = $this->testPath . '/empty';
        mkdir($empty);
        if (VanillaPhpAdapter::supports($empty)) throw new \AssertionError('Empty directory was classified as vanilla PHP.');
        if (chmod($empty, 0000)) {
            try {
                if (!is_readable($empty) && VanillaPhpAdapter::supports($empty)) throw new \AssertionError('Unreadable directory was classified as vanilla PHP.');
            } finally {
                chmod($empty, 0700);
            }
        }

        $unreadable = $this->testPath . '/unreadable';
        mkdir($unreadable);
        mkdir($unreadable . '/index.php');
        if (VanillaPhpAdapter::supports($unreadable)) throw new \AssertionError('Directory named with a PHP extension was treated as readable PHP source.');
    }

    public function testPhpCompatibilityMetadataMatchesComposerRequirement(): void
    {
        if ((VanillaPhpAdapter::getSupportedStack()['php'] ?? null) !== '>=8.0') throw new \AssertionError('PHP compatibility metadata must match DevPilot composer.json.');
    }

    public function testHealthCheckHonorsEnabledAndDisabledAllowUrlFopen(): void
    {
        mkdir($this->testPath . '/uploads');
        $enabled = $this->healthCheckInChild('1');
        $disabled = $this->healthCheckInChild('0');
        $warning = 'allow_url_fopen may be disabled';
        if (in_array($warning, $enabled['warnings'], true)) throw new \AssertionError('Enabled allow_url_fopen was reported disabled.');
        if (!in_array($warning, $disabled['warnings'], true)) throw new \AssertionError('Disabled allow_url_fopen was not reported.');
    }

    public function testMissingAndSecretEnvironmentFilesAreHandledSafely(): void
    {
        mkdir($this->testPath . '/uploads');
        $adapter = new VanillaPhpAdapter($this->testPath);
        $missing = $adapter->discover();
        if (($missing['database'] ?? null) !== null) throw new \AssertionError('Missing env files produced a database detection.');

        $secret = 'DVP_TEST_SECRET_9af3';
        file_put_contents($this->testPath . '/.env', "DB_HOST=mysql\nDB_PASSWORD={$secret}\n");
        $discovered = $adapter->discover();
        $output = json_encode($discovered, JSON_THROW_ON_ERROR);
        if (($discovered['database'] ?? null) !== 'mysql') throw new \AssertionError('Sanitized database type was not detected.');
        if (str_contains($output, $secret) || str_contains($output, 'DB_PASSWORD')) throw new \AssertionError('Environment secrets appeared in discovery output.');
        if (($discovered['environmentFiles'] ?? []) !== ['.env']) throw new \AssertionError('Environment discovery exposed paths or unexpected content.');
    }

    private function healthCheckInChild(string $setting): array
    {
        $code = 'require $argv[1]; $adapter = new \\DevPilot\\Adapters\\Php\\VanillaPhpAdapter($argv[2]); echo json_encode($adapter->checkHealth(), JSON_THROW_ON_ERROR);';
        $process = proc_open([PHP_BINARY,'-d','allow_url_fopen=' . $setting,'-r',$code,dirname(__DIR__) . '/autoload.php',$this->testPath], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new \RuntimeException('Could not start health-check subprocess.');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $exitCode = proc_close($process);
        $result = json_decode((string) $stdout, true);
        if ($exitCode !== 0 || !is_array($result)) throw new \RuntimeException('Health-check subprocess failed: ' . trim((string) $stderr));
        return $result;
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
