<?php declare(strict_types=1);

namespace Salient\Tests\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Salient\Container\Application;
use Salient\Container\ApplicationInterface;
use Salient\Container\Container;
use Salient\Container\ContainerInterface;
use Salient\Core\Catalog\EnvFlag;
use Salient\Core\Facade\Config;
use Salient\Core\Utility\Env;
use Salient\Core\Utility\File;
use Salient\Tests\TestCase;

final class ApplicationTest extends TestCase
{
    private const CONFIG = [
        'app' => [
            'name' => 'My App',
        ],
        'services' => [],
    ];

    public function testBindContainer(): void
    {
        $app = new Application();
        $this->assertTrue($app->has(PsrContainerInterface::class));
        $this->assertTrue($app->has(ContainerInterface::class));
        $this->assertTrue($app->has(ApplicationInterface::class));
        $this->assertTrue($app->has(Container::class));
        $this->assertTrue($app->has(Application::class));
        $this->assertSame($app, $app->get(PsrContainerInterface::class));
        $this->assertSame($app, $app->get(ContainerInterface::class));
        $this->assertSame($app, $app->get(ApplicationInterface::class));
        $this->assertSame($app, $app->get(Container::class));
        $this->assertSame($app, $app->get(Application::class));
        $app->unload();
    }

    public function testConfigDir(): void
    {
        $this->assertSame([], Config::all());

        $basePath = File::createTempDir();
        $configDir = $basePath . '/config';
        File::createDir($configDir);
        foreach (self::CONFIG as $name => $data) {
            $data = sprintf(
                '<?php return %s;' . \PHP_EOL,
                var_export($data, true),
            );
            File::putContents("{$configDir}/{$name}.php", $data);
        }

        $app = new Application($basePath, null, EnvFlag::ALL, null);
        $this->assertSame([], Config::all());

        $app = new Application($basePath);
        $this->assertSame(self::CONFIG, Config::all());

        $app->unload();
        Config::unload();

        File::pruneDir($basePath);
        rmdir($basePath);
    }

    /**
     * @backupGlobals enabled
     */
    public function testPaths(): void
    {
        // realpath provides cross-platform normalisation here
        $basePath = realpath(File::createTempDir()) ?: '';
        $homeDir = realpath(Env::home()) ?: '';
        $this->assertDirectoryExists($basePath);
        $this->assertDirectoryExists($homeDir);

        $_ENV['app_env'] = 'test';
        $app = new Application($basePath);
        $this->assertSame($basePath, $app->getBasePath());
        $this->assertSame($basePath . '/var/cache', $cachePath = $app->getCachePath());
        $this->assertSame($basePath . '/config', $configPath = $app->getConfigPath());
        $this->assertSame($basePath . '/var/lib', $dataPath = $app->getDataPath());
        $this->assertSame($basePath . '/var/log', $logPath = $app->getLogPath());
        $this->assertSame($basePath . '/var/tmp', $tempPath = $app->getTempPath());
        $this->assertDirectoryExists($cachePath);
        $this->assertDirectoryExists($configPath);
        $this->assertDirectoryExists($dataPath);
        $this->assertDirectoryExists($logPath);
        $this->assertDirectoryExists($tempPath);
        $app->unload();

        $_ENV['app_env'] = 'production';
        $app = new Application($basePath);
        $this->assertSame($basePath, $app->getBasePath());
        $this->assertStringStartsWith("$homeDir" . \DIRECTORY_SEPARATOR, $app->getCachePath(false));
        $this->assertStringStartsWith("$homeDir" . \DIRECTORY_SEPARATOR, $app->getConfigPath(false));
        $this->assertStringStartsWith("$homeDir" . \DIRECTORY_SEPARATOR, $app->getDataPath(false));
        $this->assertStringStartsWith("$homeDir" . \DIRECTORY_SEPARATOR, $app->getLogPath(false));
        $this->assertStringStartsWith("$homeDir" . \DIRECTORY_SEPARATOR, $app->getTempPath(false));
        $app->unload();

        File::pruneDir($basePath);
        rmdir($basePath);
    }
}
