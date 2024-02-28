<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Catalog\Core\MessageLevel as Level;
use Salient\Catalog\Core\MessageLevelGroup as LevelGroup;
use Salient\Cli\CliApplication;
use Salient\Console\Target\MockTarget;
use Salient\Contract\Cli\CliApplicationInterface;
use Salient\Contract\Cli\CliCommandInterface;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\File;

abstract class CommandTestCase extends TestCase
{
    protected function startApp(CliApplicationInterface $app): CliApplicationInterface
    {
        return $app;
    }

    /**
     * @return array<class-string,class-string>
     */
    protected function getServices(): array
    {
        return [];
    }

    /**
     * @param mixed ...$args
     */
    protected function makeCommandAssertions(
        CliApplicationInterface $app,
        CliCommandInterface $command,
        ...$args
    ): void {}

    /**
     * @param class-string<CliCommandInterface> $command
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
        Console::registerTarget($target, LevelGroup::ALL_EXCEPT_DEBUG);

        $this->expectOutputString($output);

        $basePath = File::createTempDir();
        $app = new CliApplication($basePath);

        try {
            $app = $this->startApp($app);
            $app = $app->providers($this->getServices());
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
            Console::unload();
        }
    }
}
