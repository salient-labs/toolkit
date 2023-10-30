<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Container\Application;
use Lkrms\Utility\Env;
use Lkrms\Utility\File;

final class ApplicationTest extends \Lkrms\Tests\TestCase
{
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
        $this->assertStringStartsWith("$homeDir" . DIRECTORY_SEPARATOR, $app->getCachePath(false));
        $this->assertStringStartsWith("$homeDir" . DIRECTORY_SEPARATOR, $app->getConfigPath(false));
        $this->assertStringStartsWith("$homeDir" . DIRECTORY_SEPARATOR, $app->getDataPath(false));
        $this->assertStringStartsWith("$homeDir" . DIRECTORY_SEPARATOR, $app->getLogPath(false));
        $this->assertStringStartsWith("$homeDir" . DIRECTORY_SEPARATOR, $app->getTempPath(false));
        $app->unload();

        File::pruneDir($basePath);
        rmdir($basePath);
    }
}
