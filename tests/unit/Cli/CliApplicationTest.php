<?php declare(strict_types=1);

namespace Lkrms\Tests\Cli;

use Lkrms\Cli\CliApplication;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleLevelGroup as LevelGroup;
use Lkrms\Console\Target\MockTarget;
use Lkrms\Facade\Console;
use Lkrms\Tests\Cli\Command\TestOptions;
use Lkrms\Tests\TestCase;
use Lkrms\Utility\File;
use LogicException;

/**
 * @backupGlobals enabled
 */
final class CliApplicationTest extends TestCase
{
    private static string $BasePath;

    private CliApplication $App;

    private MockTarget $ConsoleTarget;

    public static function setUpBeforeClass(): void
    {
        self::$BasePath = File::createTempDir();
    }

    protected function setUp(): void
    {
        $this->ConsoleTarget = new MockTarget();
        Console::registerTarget($this->ConsoleTarget, LevelGroup::ALL_EXCEPT_DEBUG);

        $_SERVER['SCRIPT_FILENAME'] = 'app';

        $this->App = new CliApplication(self::$BasePath);
    }

    protected function tearDown(): void
    {
        $this->App->unload();

        Console::deregisterTarget($this->ConsoleTarget);
        Console::unload();
    }

    public static function tearDownAfterClass(): void
    {
        File::pruneDir(self::$BasePath);
        rmdir(self::$BasePath);
    }

    public function testCommandSynopsis(): void
    {
        $_SERVER['argv'] = ['app.php'];
        $app = $this->App->oneCommand(TestOptions::class);
        $this->assertSame(1, $app->run()->getLastExitStatus());
        $this->assertSame([
            [Level::ERROR, 'Error: --start required'],
            [Level::INFO, <<<'EOF'

                app [-fF] [--nullable] [-v <entity>] [-V <value>,...] -s <date>

                See 'app --help' for more information.
                EOF],
        ], $this->ConsoleTarget->getMessages());
    }

    public function testCommandHelp(): void
    {
        $_SERVER['argv'] = ['app.php', '--help'];
        $app = $this->App->oneCommand(TestOptions::class);
        $this->assertSame(0, $app->run()->getLastExitStatus());
        $this->assertSame([
            [Level::INFO, <<<'EOF'
                NAME
                    app - Test CliCommand options

                SYNOPSIS
                    app [-fF] [--nullable] [-v entity] [-V value,...] -s date

                OPTIONS
                    -f, --flag
                        Flag

                    -F, --flags
                        Flag with multipleAllowed()

                    --nullable
                        Flag with nullable() and no short form

                    -v, --value entity
                        Value with defaultValue() and valueName entity

                        The default entity is: foo

                    -V, --values value,...
                        Value with multipleAllowed(), unique() and nullable()

                    -s, --start date
                        Value with required(), valueType DATE and valueName date
                EOF],
        ], $this->ConsoleTarget->getMessages());
    }

    public function testCommandOptionValues(): void
    {
        $_SERVER['argv'] = ['app.php', '--start', '2024-01-18T10:00:00+11:00'];
        $this->expectOutputString(<<<'EOF'
            {
                "args": [],
                "options": {
                    "start": {
                        "date": "2024-01-18 10:00:00.000000",
                        "timezone_type": 1,
                        "timezone": "+11:00"
                    }
                },
                "bound": {
                    "Flag": false,
                    "RepeatableFlag": 0,
                    "NullableFlag": null,
                    "Value": "foo",
                    "RepeatableValue": [],
                    "RequiredValue": {
                        "date": "2024-01-18 10:00:00.000000",
                        "timezone_type": 1,
                        "timezone": "+11:00"
                    }
                }
            }

            EOF);
        $app = $this->App->oneCommand(TestOptions::class);
        $this->assertSame(0, $app->run()->getLastExitStatus());
    }

    public function testCommandCollision(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Another command has been registered at 'options'");

        $this
            ->App
            ->command(['options', 'test'], TestOptions::class)
            ->command(['options'], TestOptions::class);
    }
}
