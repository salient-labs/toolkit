<?php declare(strict_types=1);

namespace Salient\Tests\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Salient\Container\Application;
use Salient\Container\Container;
use Salient\Contract\Container\ApplicationInterface;
use Salient\Contract\Container\ContainerInterface;
use Salient\Core\Facade\Config;
use Salient\Tests\HttpTestCase;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Get;

/**
 * @covers \Salient\Container\Application
 */
final class ApplicationTest extends HttpTestCase
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
            File::writeContents($configDir . "/$name.php", $data);
        }

        $app = new Application($basePath, null, Env::APPLY_ALL, null);
        $this->assertSame([], Config::all());

        $app = new Application($basePath);
        $this->assertSame(self::CONFIG, Config::all());

        $app->unload();
        Config::unload();
        File::pruneDir($basePath, true);
    }

    /**
     * @backupGlobals enabled
     */
    public function testPaths(): void
    {
        // realpath provides cross-platform normalisation here
        $basePath = File::createTempDir();
        $this->assertIsString($basePath = realpath($basePath));
        $homeDir = Env::getHomeDir();
        $this->assertNotNull($homeDir);
        $this->assertIsString($homeDir = realpath($homeDir));
        $this->assertDirectoryExists($basePath);
        $this->assertDirectoryExists($homeDir);

        $_ENV['app_env'] = 'test';
        $app = new Application($basePath);
        $this->assertSame($basePath, $app->getBasePath());
        $this->assertSame($basePath . '/var/cache', $cachePath = $app->getCachePath());
        $this->assertSame($basePath . '/var/lib/config', $configPath = $app->getConfigPath());
        $this->assertSame($basePath . '/var/lib/data', $dataPath = $app->getDataPath());
        $this->assertSame($basePath . '/var/log', $logPath = $app->getLogPath());
        $this->assertSame($basePath . '/var/tmp', $tempPath = $app->getTempPath());
        $this->assertDirectoryExists($cachePath);
        $this->assertDirectoryExists($configPath);
        $this->assertDirectoryExists($dataPath);
        $this->assertDirectoryExists($logPath);
        $this->assertDirectoryExists($tempPath);

        $_ENV['app_env'] = 'production';
        $app = new Application($basePath);
        $this->assertSame($basePath, $app->getBasePath());
        $this->assertStringStartsWith($homeDir . \DIRECTORY_SEPARATOR, $app->getCachePath(false));
        $this->assertStringStartsWith($homeDir . \DIRECTORY_SEPARATOR, $app->getConfigPath(false));
        $this->assertStringStartsWith($homeDir . \DIRECTORY_SEPARATOR, $app->getDataPath(false));
        $this->assertStringStartsWith($homeDir . \DIRECTORY_SEPARATOR, $app->getLogPath(false));
        $this->assertStringStartsWith($homeDir . \DIRECTORY_SEPARATOR, $app->getTempPath(false));

        $app->unload();
        File::pruneDir($basePath, true);
    }

    /**
     * @backupGlobals enabled
     */
    public function testExportHar(): void
    {
        $basePath = File::createTempDir();

        $_ENV['app_env'] = 'test';
        $app = new Application($basePath);
        /** @var string|null */
        $uuid = null;
        $app->exportHar(
            null,
            'app',
            'v1.0.0',
            function () use (&$uuid) { return $uuid = Get::uuid(); },
        );
        $this->assertNull($uuid);
        $this->assertNull($app->getHarFilename());
        $this->startHttpServer();
        $this->getCurler()->get();
        // @phpstan-ignore method.impossibleType
        $this->assertNotNull($uuid);
        $this->assertNotNull($file = $app->getHarFilename());
        $this->assertStringEndsWith('-' . $uuid . '.har', $file);
        $app->unload();
        $this->assertTrue(File::same($file, $basePath . '/var/log/' . basename($file)));
        $this->assertStringStartsWith(
            '{"log":{"version":"1.2","creator":{"name":"app","version":"v1.0.0"},"pages":[],"entries":[{',
            File::getContents($file),
        );

        File::pruneDir($basePath, true);
    }
}
