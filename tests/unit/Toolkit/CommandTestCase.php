<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Cli\CliApplication;
use Salient\Contract\Catalog\MessageLevelGroup as LevelGroup;
use Salient\Contract\Cli\CliApplicationInterface;
use Salient\Contract\Cli\CliCommandInterface;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Err;
use Salient\Core\Facade\Profile;
use Salient\Testing\Console\MockTarget;
use Salient\Utility\File;

abstract class CommandTestCase extends TestCase
{
    /**
     * @param class-string<CliCommandInterface> $command
     * @param string[] $args
     * @param string[] $name
     * @param array<array{Console::LEVEL_*,string,2?:array<string,mixed>}>|null $consoleMessages
     * @param (callable(CliApplicationInterface, CliCommandInterface): mixed)|null $callback
     */
    public function assertCommandProduces(
        string $output,
        int $exitStatus,
        string $command,
        array $args,
        array $name = [],
        bool $outputHasConsoleMessages = true,
        bool $outputHasDebugMessages = false,
        ?array $consoleMessages = null,
        int $runs = 1,
        ?callable $callback = null,
        bool $targetIsTty = false
    ): void {
        $_SERVER['SCRIPT_FILENAME'] = 'app';
        $_SERVER['argv'] = ['app', ...$args];

        Console::registerTarget(
            $target = new MockTarget(
                $outputHasConsoleMessages
                    ? File::open('php://output', '')
                    : null,
                true,
                true,
                $targetIsTty,
            ),
            $outputHasDebugMessages
                ? LevelGroup::ALL
                : LevelGroup::ALL_EXCEPT_DEBUG,
        );

        $basePath = File::createTempDir();
        $app = (new CliApplication($basePath))->command($name, $command);

        $this->expectOutputString($output);

        try {
            $app = $this->setUpApp($app);

            for ($i = 0; $i < $runs; $i++) {
                $status = $app->run()->getLastExitStatus();
                $this->assertSame($exitStatus, $status, 'exit status');
            }

            if ($consoleMessages !== null) {
                $this->assertSameConsoleMessages(
                    $consoleMessages,
                    $target->getMessages()
                );
            }

            if ($callback !== null) {
                /** @var CliCommandInterface */
                $command = $app->getLastCommand();
                $callback($app, $command);
            }
        } finally {
            $app->unload();
            File::pruneDir($basePath, true);
            Profile::unload();
            Console::unload();
            Err::unload();
        }
    }

    protected function setUpApp(CliApplicationInterface $app): CliApplicationInterface
    {
        return $app;
    }
}
