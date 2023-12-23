<?php declare(strict_types=1);

namespace Lkrms\Tests\Cli;

use Lkrms\Cli\CliApplication;
use Lkrms\Tests\Cli\Command\TestOptions;
use Lkrms\Tests\TestCase;
use Lkrms\Utility\File;
use LogicException;

final class CliApplicationTest extends TestCase
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
