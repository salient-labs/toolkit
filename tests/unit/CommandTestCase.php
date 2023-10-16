<?php declare(strict_types=1);

namespace Lkrms\Tests;

use Lkrms\Cli\Contract\ICliCommand;
use Lkrms\Cli\CliApplication;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleLevels as Levels;
use Lkrms\Contract\IService;
use Lkrms\Facade\Console;
use Lkrms\Facade\File;
use Lkrms\Tests\Console\MockTarget;

abstract class CommandTestCase extends \Lkrms\Tests\TestCase
{
    protected function startApp(CliApplication $app): CliApplication
    {
        return $app;
    }

    /**
     * @return array<class-string|int,class-string<IService>>
     */
    protected function getServices(): array
    {
        return [];
    }

    /**
     * @param class-string<ICliCommand> $command
     * @param string[] $args
     * @param array<array{Level::*,string}>|null $consoleOutput
     */
    public function assertCommandProduces(
        string $output,
        int $exitStatus,
        string $command,
        array $args,
        ?array $consoleOutput = null
    ): void {
        $target = new MockTarget(
            true,
            true,
            true,
            $consoleOutput === null
                ? fopen('php://output', 'a')
                : null,
        );

        Console::registerTarget(
            $target,
            $consoleOutput === null
                ? Levels::ALL_EXCEPT_DEBUG
                : array_unique(array_map(fn($message) => $message[0], $consoleOutput))
        );

        $this->expectOutputString(str_replace(PHP_EOL, "\n", $output));

        $basePath = File::createTemporaryDirectory();
        $app = new CliApplication($basePath);

        try {
            $app = $this->startApp($app);
            $app = $app->services($this->getServices());
            $command = $app->get($command);
            $status = $command(...$args);
            $this->assertSame($exitStatus, $status, 'exit status');
            if ($consoleOutput !== null) {
                $this->assertSame($consoleOutput, $target->getMessages());
            }
        } finally {
            $app->unload();

            File::pruneDirectory($basePath);
            rmdir($basePath);

            Console::deregisterTarget($target);
        }
    }
}
