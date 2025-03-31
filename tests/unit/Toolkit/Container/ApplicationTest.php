<?php declare(strict_types=1);

namespace Salient\Tests\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Salient\Container\Application;
use Salient\Container\Container;
use Salient\Contract\Container\ApplicationInterface;
use Salient\Contract\Container\ContainerInterface;
use Salient\Core\Facade\Config;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Err;
use Salient\Core\Facade\Sync;
use Salient\Tests\Sync\Entity\Provider\PostProvider;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\HttpTestCase;
use Salient\Utility\Exception\FilesystemErrorException;
use Salient\Utility\Exception\InvalidEnvironmentException;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Package;

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

    private const ENV = [
        '.env' => <<<'EOF'
value0=default
value1=default
value2=default

EOF,
        '.env.test' => <<<'EOF'
value0=test
value1=test

EOF,
    ];

    private string $BasePath;
    private ?Application $App = null;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->BasePath = File::createTempDir();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        if ($this->App) {
            $this->App->unload();
            $this->App = null;
        }
        Err::unload();
        Console::unload();
        File::pruneDir($this->BasePath, true);
        parent::tearDown();
    }

    public function testBindsContainer(): void
    {
        $app = $this->getApp();
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
    }

    public function testNoBasePath(): void
    {
        $this->App = new Application();
        $this->assertSame(Package::path(), $this->App->getBasePath());
    }

    public function testInvalidBasePath(): void
    {
        $this->expectException(FilesystemErrorException::class);
        $this->expectExceptionMessage('Invalid base path: ');
        $this->App = new Application($this->BasePath . '/does_not_exist');
    }

    /**
     * @backupGlobals enabled
     */
    public function testInvalidBasePathInEnvironment(): void
    {
        $this->expectException(InvalidEnvironmentException::class);
        $this->expectExceptionMessage('Invalid base path: ');
        $_ENV['app_base_path'] = $this->BasePath . '/does_not_exist';
        $this->App = new Application();
    }

    /**
     * @runInSeparateProcess
     */
    public function testLoadsEnvFiles(): void
    {
        foreach (self::ENV as $name => $data) {
            File::writeContents($this->BasePath . "/$name", $data);
        }

        $_ENV['value0'] = __METHOD__;
        $reset = function () {
            Env::unset('value1');
            Env::unset('value2');
        };
        $get = fn() => [
            Env::get('value0', null),
            Env::get('value1', null),
            Env::get('value2', null),
        ];

        $_ENV['app_env'] = 'test';
        $reset();
        $this->getApp();
        $this->assertSame([__METHOD__, 'test', null], $get());

        $_ENV['app_env'] = 'production';
        $reset();
        $this->getApp();
        $this->assertSame([__METHOD__, 'default', 'default'], $get());

        unset($_ENV['app_env']);
        $reset();
        $this->getApp();
        $this->assertSame([__METHOD__, 'default', 'default'], $get());
    }

    public function testLoadsConfigDir(): void
    {
        $this->assertSame([], Config::all());

        File::createDir($configDir = $this->BasePath . '/config');
        foreach (self::CONFIG as $name => $data) {
            $data = sprintf(
                '<?php return %s;' . \PHP_EOL,
                var_export($data, true),
            );
            File::writeContents("$configDir/$name.php", $data);
        }

        $app = $this->getApp(null, Env::APPLY_ALL, null);
        $this->assertSame([], Config::all());
        $this->assertSame('phpunit', $app->getName());

        $app = $this->getApp();
        $this->assertSame(self::CONFIG, Config::all());
        $this->assertSame('My App', $app->getName());

        Config::unload();
    }

    /**
     * @backupGlobals enabled
     */
    public function testPaths(): void
    {
        // `realpath()` is used for cross-platform normalisation here
        $this->assertIsString($basePath = realpath($this->BasePath));
        $this->BasePath = $basePath;
        $this->assertNotNull($homeDir = Env::getHomeDir());
        $this->assertIsString($homeDir = realpath($homeDir));
        $this->assertDirectoryExists($basePath);
        $this->assertDirectoryExists($homeDir);

        $_ENV['app_env'] = 'test';
        $app = $this->getApp();
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
        $app = $this->getApp();
        $this->assertSame($basePath, $app->getBasePath());
        $this->assertStringStartsWith($homeDir . \DIRECTORY_SEPARATOR, $app->getCachePath(false));
        $this->assertStringStartsWith($homeDir . \DIRECTORY_SEPARATOR, $app->getConfigPath(false));
        $this->assertStringStartsWith($homeDir . \DIRECTORY_SEPARATOR, $app->getDataPath(false));
        $this->assertStringStartsWith($homeDir . \DIRECTORY_SEPARATOR, $app->getLogPath(false));
        $this->assertStringStartsWith($homeDir . \DIRECTORY_SEPARATOR, $app->getTempPath(false));

        $_ENV['app_cache_path'] = '.cache';
        $_ENV['app_config_path'] = '.config';
        $_ENV['app_data_path'] = '.data';
        $_ENV['app_log_path'] = '.log';
        $_ENV['app_temp_path'] = $basePath . '/.tmp';
        $app = $this->getApp();
        $this->assertSame($basePath . '/.cache', $cachePath = $app->getCachePath());
        $this->assertSame($basePath . '/.config', $configPath = $app->getConfigPath());
        $this->assertSame($basePath . '/.data', $dataPath = $app->getDataPath());
        $this->assertSame($basePath . '/.log', $logPath = $app->getLogPath());
        $this->assertSame($basePath . '/.tmp', $tempPath = $app->getTempPath());
        $this->assertDirectoryExists($cachePath);
        $this->assertDirectoryExists($configPath);
        $this->assertDirectoryExists($dataPath);
        $this->assertDirectoryExists($logPath);
        $this->assertDirectoryExists($tempPath);

        $_ENV['app_config_path'] = '';
        $app = $this->getApp();
        $this->expectException(InvalidEnvironmentException::class);
        $this->expectExceptionMessage('Invalid config path in environment variable: app_config_path');
        $app->getConfigPath();
    }

    /**
     * @backupGlobals enabled
     */
    public function testInvalidPath(): void
    {
        $_ENV['XDG_CONFIG_HOME'] = 'does_not_exist';
        $_ENV['APPDATA'] = 'does_not_exist';
        $_ENV['app_env'] = 'production';
        $app = $this->getApp();
        $this->expectException(InvalidEnvironmentException::class);
        $this->expectExceptionMessage('Invalid config path: does_not_exist/');
        $app->getConfigPath();
    }

    /**
     * @backupGlobals enabled
     */
    public function testRecordHar(): void
    {
        $_ENV['app_env'] = 'test';
        $app = $this->getApp();
        $this->assertFalse($app->hasHarRecorder());
        /** @var string|null */
        $uuid = null;
        $app->recordHar(
            null,
            'app',
            'v1.0.0',
            function () use (&$uuid) { return $uuid = Get::uuid(); },
        );
        $this->assertTrue($app->hasHarRecorder());
        $this->assertNull($uuid);
        $this->assertNull($app->getHarFilename());
        $this->startHttpServer();
        $this->getCurler()->get();
        // @phpstan-ignore method.impossibleType
        $this->assertNotNull($uuid);
        // @phpstan-ignore method.impossibleType
        $this->assertNotNull($file = $app->getHarFilename());
        $this->assertStringEndsWith('-' . $uuid . '.har', $file);
        $this->assertTrue($app->hasHarRecorder());
        $app->unload();
        $this->assertFalse($app->hasHarRecorder());
        $this->assertTrue(File::same($file, $this->BasePath . '/var/log/har/' . basename($file)));
        $this->assertStringStartsWith(
            '{"log":{"version":"1.2","creator":{"name":"app","version":"v1.0.0"},"pages":[],"entries":[{',
            File::getContents($file),
        );
    }

    /**
     * @backupGlobals enabled
     */
    public function testRecordHarWithoutRequests(): void
    {
        $_ENV['app_env'] = 'test';
        $app = $this->getApp();
        $app->recordHar();
        $app->unload();
        $this->assertDirectoryDoesNotExist($this->BasePath . '/var/log/har');
    }

    /**
     * @backupGlobals enabled
     */
    public function testRecordHarWithSync(): void
    {
        $_ENV['app_env'] = 'test';
        $app = ($this->getApp())
            ->recordHar(null, 'app', 'v1.0.0')
            ->provider(JsonPlaceholderApi::class)
            ->startSync(static::class, []);
        $this->assertNull($app->getHarFilename());
        // Trigger the start of a run
        $app->get(PostProvider::class)->with(Post::class)->get(1);
        $uuid = Sync::getRunUuid();
        // @phpstan-ignore method.impossibleType
        $this->assertNotNull($file = $app->getHarFilename());
        $this->assertStringEndsWith('-' . $uuid . '.har', $file);
    }

    /**
     * @param non-empty-string|null $name
     * @param int-mask-of<Env::APPLY_*> $envFlags
     */
    private function getApp(
        ?string $name = null,
        int $envFlags = Env::APPLY_ALL,
        ?string $configDir = 'config'
    ): Application {
        if ($this->App) {
            $this->App->unload();
        }
        return $this->App = new Application(
            $this->BasePath,
            $name,
            $envFlags,
            $configDir,
        );
    }
}
