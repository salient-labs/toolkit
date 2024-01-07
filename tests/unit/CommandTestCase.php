<?php declare(strict_types=1);

namespace Lkrms\Tests;

use Lkrms\Cli\Contract\ICliCommand;
use Lkrms\Cli\CliApplication;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleLevelGroup as Levels;
use Lkrms\Console\Target\MockTarget;
use Lkrms\Contract\IService;
use Lkrms\Facade\Console;
use Lkrms\Utility\File;

abstract class CommandTestCase extends TestCase
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
     * @param mixed ...$args
     */
    protected function makeCommandAssertions(
        CliApplication $app,
        ICliCommand $command,
        ...$args
    ): void {}

    /**
     * @param class-string<ICliCommand> $command
     * @param string[] $args
     * @param string[] $name
     * @param array<array{Level::*,string,2?:array<string,mixed>}>|null $consoleMessages
     */
    public function assertCommandProduces(
        string $output,
        int $exitStatus,
        string $command,
        array $args,
        array $name = [],
        bool $outputIncludesConsoleMessages = true,
        ?array $consoleMessages = null,
        int $runs = 1
    ): void {
        $target = $outputIncludesConsoleMessages
            ? new MockTarget(File::open('php://output', ''))
            : new MockTarget();
        Console::registerTarget($target, Levels::ALL_EXCEPT_DEBUG);

        $this->expectOutputString($output);

        $basePath = File::createTempDir();
        $app = new CliApplication($basePath);

        try {
            $app = $this->startApp($app);
            $app = $app->services($this->getServices());
            $command = $app->get($command);
            $command->setName($name);
            for ($i = 0; $i < $runs; $i++) {
                $status = $command(...$args);
                $this->assertSame($exitStatus, $status, 'exit status');
            }
            if ($consoleMessages !== null) {
                $this->assertSame($consoleMessages, $target->getMessages());
            }
            $this->makeCommandAssertions($app, $command, ...func_get_args());
        } finally {
            $app->unload();

            File::pruneDir($basePath);
            rmdir($basePath);

            Console::deregisterTarget($target);
        }
    }
}
