<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Container\Application;
use Lkrms\Facade\File;
use Lkrms\Utility\Env;

final class ApplicationTest extends \Lkrms\Tests\TestCase
{
    /**
     * @backupGlobals enabled
     */
    public function testPaths(): void
    {
        $basePath = File::createTemporaryDirectory();
        $homeDir = realpath(Env::home()) ?: '';
        $this->assertDirectoryExists($basePath);
        $this->assertDirectoryExists($homeDir);

        $_ENV['PHP_ENV'] = 'test';
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

        $_ENV['PHP_ENV'] = 'production';
        $app = new Application($basePath);
        $this->assertSame($basePath, $app->getBasePath());
        $this->assertStringStartsWith("$homeDir/", $app->getCachePath(false));
        $this->assertStringStartsWith("$homeDir/", $app->getConfigPath(false));
        $this->assertStringStartsWith("$homeDir/", $app->getDataPath(false));
        $this->assertStringStartsWith("$homeDir/", $app->getLogPath(false));
        $this->assertStringStartsWith("$homeDir/", $app->getTempPath(false));

        File::pruneDirectory($basePath);
        rmdir($basePath);
    }
}
