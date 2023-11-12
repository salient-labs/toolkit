<?php declare(strict_types=1);

namespace Lkrms\Tests;

use Lkrms\Cli\Contract\ICliCommand;
use Lkrms\Cli\CliApplication;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleLevels as Levels;
use Lkrms\Console\Target\MockTarget;
use Lkrms\Contract\IService;
use Lkrms\Facade\Console;
use Lkrms\Utility\File;

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
     * @param array<array{Level::*,string}>|null $consoleOutput
     */
    public function assertCommandProduces(
        string $output,
        int $exitStatus,
        string $command,
        array $args,
        ?array $consoleOutput = null,
        int $runs = 1
    ): void {
        $f = fopen('php://output', '');
        $target = new MockTarget(true, true, true, 80, $f);
        Console::registerTarget($target, Levels::ALL_EXCEPT_DEBUG);

        $this->expectOutputString(str_replace(\PHP_EOL, "\n", $output));

        $basePath = File::createTempDir();
        $app = new CliApplication($basePath);

        try {
            $app = $this->startApp($app);
            $app = $app->services($this->getServices());
            $command = $app->get($command);
            for ($i = 0; $i < $runs; $i++) {
                $status = $command(...$args);
                $this->assertSame($exitStatus, $status, 'exit status');
            }
            if ($consoleOutput !== null) {
                $this->assertSame($consoleOutput, $target->getMessages());
            }
            $this->makeCommandAssertions($app, $command, ...func_get_args());
        } finally {
            $app->unload();

            File::pruneDir($basePath);
            rmdir($basePath);

            Console::deregisterTarget($target);
            fclose($f);
        }
    }
}
