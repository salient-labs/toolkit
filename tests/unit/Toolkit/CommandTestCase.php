<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Cli\CliApplication;
use Salient\Console\Target\MockTarget;
use Salient\Contract\Cli\CliApplicationInterface;
use Salient\Contract\Cli\CliCommandInterface;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Core\MessageLevelGroup as LevelGroup;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\File;

abstract class CommandTestCase extends TestCase
{
    /**
     * @param class-string<CliCommandInterface> $command
     * @param string[] $args
     * @param string[] $name
     * @param array<array{Level::*,string,2?:array<string,mixed>}>|null $consoleMessages
     * @param (callable(CliApplicationInterface, CliCommandInterface): mixed)|null $callback
     */
    public function assertCommandProduces(
        string $output,
        int $exitStatus,
        string $command,
        array $args,
        array $name = [],
        bool $outputIncludesConsoleMessages = true,
        bool $debugMessagesAreIncluded = false,
        ?array $consoleMessages = null,
        int $runs = 1,
        ?callable $callback = null
    ): void {
        Console::registerTarget(
            $target = $outputIncludesConsoleMessages
                ? new MockTarget(File::open('php://output', ''))
                : new MockTarget(),
            $debugMessagesAreIncluded
                ? LevelGroup::ALL
                : LevelGroup::ALL_EXCEPT_DEBUG,
        );

        $basePath = File::createTempDir();
        $app = new CliApplication($basePath);

        $this->expectOutputString($output);

        try {
            $app = $this->setUpApp($app);

            $command = $app->get($command);
            $command->setName($name);

            for ($i = 0; $i < $runs; $i++) {
                $status = $command(...$args);
                $this->assertSame($exitStatus, $status, 'exit status');
            }

            if ($consoleMessages !== null) {
                $this->assertSameConsoleMessages(
                    $consoleMessages,
                    $target->getMessages()
                );
            }

            if ($callback !== null) {
                $callback($app, $command);
            }
        } finally {
            $app->unload();
            File::pruneDir($basePath, true);
            Console::unload();
        }
    }

    protected function setUpApp(CliApplicationInterface $app): CliApplicationInterface
    {
        return $app;
    }
}
