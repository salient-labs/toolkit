<?php declare(strict_types=1);

namespace Lkrms\Tests\Cli;

use Lkrms\Cli\Contract\CliApplicationInterface;
use Lkrms\Cli\CliApplication;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleLevelGroup as LevelGroup;
use Lkrms\Console\Target\MockTarget;
use Lkrms\Facade\Console;
use Lkrms\Tests\Cli\Command\TestOptions;
use Lkrms\Tests\TestCase;
use Lkrms\Utility\File;
use Lkrms\Utility\Json;
use LogicException;

/**
 * @backupGlobals enabled
 */
final class CliApplicationTest extends TestCase
{
    private static string $BasePath;

    private CliApplicationInterface $App;

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
        $_ENV['required'] = 'start';

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

    public function testGetLastCommand(): void
    {
        $_SERVER['argv'] = ['app.php', '--help'];
        $this->App->command(['options', 'test'], TestOptions::class);
        $this->assertNull($this->App->getLastCommand());
        $this->assertNull($this->App->run()->getLastCommand());
        $_SERVER['argv'] = ['app.php', 'options', 'test'];
        $command = $this->App->run()->getLastCommand();
        $this->assertInstanceOf(TestOptions::class, $command);
        /** @var TestOptions $command */
        $this->assertSame(1, $command->getRuns());
    }

    /**
     * @dataProvider commandProvider
     *
     * @param string[] $args
     * @param array<array{Level::*,string,2?:array<string,mixed>}>|null $consoleMessages
     * @param array<string,string>|null $env
     */
    public function testCommand(
        ?string $output,
        int $exitStatus,
        array $args = [],
        ?array $consoleMessages = null,
        ?array $env = null
    ): void {
        $_SERVER['argv'] = ['app.php', ...$args];
        if ($env !== null) {
            foreach ($env as $name => $value) {
                $_ENV[$name] = $value;
            }
        }
        $this->App->oneCommand(TestOptions::class);
        if ($output !== null) {
            $this->expectOutputString($output);
        }
        $this->assertSame($exitStatus, $this->App->run()->getLastExitStatus());
        if ($consoleMessages !== null) {
            $this->assertSameConsoleMessages($consoleMessages, $this->ConsoleTarget->getMessages());
        }
    }

    /**
     * @return array<array{string|null,int,2?:string[],3?:array<array{Level::*,string,2?:array<string,mixed>}>|null}>
     */
    public static function commandProvider(): array
    {
        return [
            'synopsis' => [
                null,
                1,
                [],
                [
                    [Level::ERROR, 'Error: --start required'],
                    [Level::INFO, <<<'EOF'

                        app [-fF] [--nullable] [-v <entity>] [-V <value>,...] [-r[<pattern>]] -s <date>

                        See 'app --help' for more information.
                        EOF],
                ],
            ],
            'help' => [
                null,
                0,
                ['--help'],
                [
                    [Level::INFO, <<<'EOF'
                        NAME
                            app - Test CliCommand options

                        SYNOPSIS
                            app [-fF] [--nullable] [-v entity] [-V value,...] [-r[pattern]] -s date

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
                                Value with conditional required(), valueType DATE and valueName date

                            -r, --filter-regex[=pattern]
                                VALUE_OPTIONAL with valueName pattern and a default value

                                The default pattern is: /./
                        EOF],
                ],
            ],
            'with required arguments' => [
                <<<'EOF'
                {
                    "args": [],
                    "allOptions": {
                        "flag": false,
                        "flags": 0,
                        "nullable": null,
                        "value": "foo",
                        "values": [],
                        "start": {
                            "date": "2024-01-18 10:00:00.000000",
                            "timezone_type": 1,
                            "timezone": "+11:00"
                        },
                        "help": false,
                        "version": false
                    },
                    "options": {
                        "start": {
                            "date": "2024-01-18 10:00:00.000000",
                            "timezone_type": 1,
                            "timezone": "+11:00"
                        }
                    },
                    "schemaOptions": {
                        "start": {
                            "date": "2024-01-18 10:00:00.000000",
                            "timezone_type": 1,
                            "timezone": "+11:00"
                        }
                    },
                    "hasArg": {
                        "start": true
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
                        },
                        "OptionalValue": null
                    }
                }

                EOF,
                0,
                ['--start', '2024-01-18T10:00:00+11:00', '--print=args,allOptions,options,schemaOptions,hasArg,bound'],
                [],
            ],
            'export optional value (no value given)' => [
                <<<'EOF'
                {
                    "options": {
                        "filter-regex": "/./"
                    },
                    "schemaOptions": {
                        "filterRegex": true
                    }
                }

                EOF,
                0,
                ['--filter-regex', '--print=options,schemaOptions'],
                [],
                ['required' => ''],
            ],
            'export optional value (value given)' => [
                <<<'EOF'
                {
                    "options": {
                        "filter-regex": "/\\.php/"
                    },
                    "schemaOptions": {
                        "filterRegex": "/\\.php/"
                    }
                }

                EOF,
                0,
                ['--filter-regex=/\.php/', '--print=options,schemaOptions'],
                [],
                ['required' => ''],
            ],
            'export optional value (default value given explicitly)' => [
                <<<'EOF'
                {
                    "options": {
                        "filter-regex": "/./"
                    },
                    "schemaOptions": {
                        "filterRegex": "/./"
                    }
                }

                EOF,
                0,
                ['--filter-regex=/./', '--print=options,schemaOptions'],
                [],
                ['required' => ''],
            ],
            'apply optional value (no value given)' => [
                <<<'EOF'
                {
                    "options": {
                        "filter-regex": "/./"
                    },
                    "schemaOptions": {
                        "filterRegex": true
                    }
                }

                EOF,
                0,
                [
                    '--action=apply-values',
                    '--data=' . Json::stringify(['filter-regex' => true]),
                    '--print=options,schemaOptions',
                ],
                [],
                ['required' => ''],
            ],
            'apply optional value (value given)' => [
                <<<'EOF'
                {
                    "options": {
                        "filter-regex": "/\\.php/"
                    },
                    "schemaOptions": {
                        "filterRegex": "/\\.php/"
                    }
                }

                EOF,
                0,
                [
                    '--action=apply-values',
                    '--data=' . Json::stringify(['filter-regex' => '/\.php/']),
                    '--print=options,schemaOptions',
                ],
                [],
                ['required' => ''],
            ],
            'apply optional value (no value given, schema)' => [
                <<<'EOF'
                {
                    "options": {
                        "filter-regex": "/./"
                    },
                    "schemaOptions": {
                        "filterRegex": true
                    }
                }

                EOF,
                0,
                [
                    '--action=apply-schema-values',
                    '--data=' . Json::stringify(['filterRegex' => true]),
                    '--print=options,schemaOptions',
                ],
                [],
                ['required' => ''],
            ],
            'apply optional value (value given, schema)' => [
                <<<'EOF'
                {
                    "options": {
                        "filter-regex": "/\\.php/"
                    },
                    "schemaOptions": {
                        "filterRegex": "/\\.php/"
                    }
                }

                EOF,
                0,
                [
                    '--action=apply-schema-values',
                    '--data=' . Json::stringify(['filterRegex' => '/\.php/']),
                    '--print=options,schemaOptions',
                ],
                [],
                ['required' => ''],
            ],
        ];
    }

    public function testInvalidSubcommand(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Subcommand does not start with a letter, followed by zero or more letters, numbers, hyphens or underscores: _options');
        $this->App->command(['_options', 'test'], TestOptions::class);
    }

    public function testCommandCollision(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Another command has been registered at 'options'");
        $this->App->command(['options', 'test'], TestOptions::class);
        $this->App->command(['options'], TestOptions::class);
    }
}
