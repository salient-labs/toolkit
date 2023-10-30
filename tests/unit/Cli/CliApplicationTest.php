<?php declare(strict_types=1);

namespace Lkrms\Tests\Cli;

use Lkrms\Cli\CliApplication;
use Lkrms\Tests\Cli\Command\TestOptions;
use Lkrms\Utility\File;
use LogicException;

class CliApplicationTest extends \Lkrms\Tests\TestCase
{
    public function testCommandCollision(): void
    {
        $this->expectException(LogicException::class);

        $basePath = File::createTempDir();
        $app = (new CliApplication($basePath))->command([], TestOptions::class);

        try {
            $app->command(['options', 'test'], TestOptions::class);
        } finally {
            $app->unload();
            File::pruneDir($basePath);
            rmdir($basePath);
        }
    }
}
