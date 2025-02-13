<?php declare(strict_types=1);

namespace Salient\Tests\Cli;

use Composer\InstalledVersions;
use Salient\Cli\CliApplication;
use Salient\Container\Application;
use Salient\Contract\Catalog\MessageLevel as Level;
use Salient\Contract\Catalog\MessageLevelGroup as LevelGroup;
use Salient\Contract\Cli\CliApplicationInterface;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Err;
use Salient\Core\Facade\Event;
use Salient\Testing\Console\MockTarget;
use Salient\Tests\Cli\Command\TestOptions;
use Salient\Tests\TestCase;
use Salient\Utility\Event\PackageDataReceivedEvent;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Json;
use Salient\Utility\Reflect;
use Closure;
use LogicException;
use ReflectionMethod;
use stdClass;

/**
 * @backupGlobals enabled
 *
 * @covers \Salient\Cli\CliApplication
 * @covers \Salient\Cli\CliCommand
 * @covers \Salient\Cli\CliHelpStyle
 * @covers \Salient\Cli\CliOption
 * @covers \Salient\Cli\Exception\CliInvalidArgumentsException
 */
final class CliApplicationTest extends TestCase
{
    private const PROD_PACKAGE = [
        'name' => 'test/options',
        'pretty_version' => 'v1.0.0',
        'version' => '1.0.0.0',
        'reference' => 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => [],
        'dev' => false,
    ];

    private const DEV_PACKAGE = [
        'name' => 'test/options',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => [],
        'dev' => true,
    ];

    private const NULL_REF_PACKAGE = [
        'name' => 'test/options',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => null,
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => [],
        'dev' => true,
    ];

    private static string $BasePath;
    private static int $ListenerId;
    /** @var array<string,mixed>|null */
    private static ?array $RootPackage = null;
    private CliApplicationInterface $App;
    private MockTarget $ConsoleTarget;

    public static function setUpBeforeClass(): void
    {
        self::$BasePath = File::createTempDir();
        self::$ListenerId = Event::getInstance()->listen(
            static function (PackageDataReceivedEvent $event): void {
                if (
                    self::$RootPackage !== null
                    && $event->isMethod(InstalledVersions::class, 'getRootPackage')
                ) {
                    $event->setData(self::$RootPackage);
                }
            }
        );
    }

    protected function setUp(): void
    {
        self::$RootPackage = self::PROD_PACKAGE;

        $this->ConsoleTarget = new MockTarget();
        Console::registerTarget($this->ConsoleTarget, LevelGroup::ALL_EXCEPT_DEBUG);

        $_SERVER['SCRIPT_FILENAME'] = 'app';

        $this->App = new CliApplication(self::$BasePath);
    }

    protected function tearDown(): void
    {
        $this->App->unload();

        Console::unload();
    }

    public static function tearDownAfterClass(): void
    {
        Event::removeListener(self::$ListenerId);
        File::pruneDir(self::$BasePath, true);
        Err::unload();
        parent::tearDownAfterClass();
    }

    public function testGetLastCommand(): void
    {
        $_SERVER['argv'] = ['app', '--help'];
        $this->App->command(['options', 'test'], TestOptions::class);
        $this->assertNull($this->App->getLastCommand());
        $this->assertNull($this->App->run()->getLastCommand());
        $_SERVER['argv'] = ['app', 'options', 'test'];
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
     * @param (Closure(TestOptions): mixed)|null $callback
     * @param array<string,mixed>|null $rootPackage
     */
    public function testCommand(
        ?string $output,
        int $exitStatus,
        array $args = [],
        ?array $consoleMessages = null,
        ?array $env = null,
        ?Closure $callback = null,
        ?array $rootPackage = null
    ): void {
        $_SERVER['argv'] = ['app', ...$args];
        if ($env !== null) {
            foreach ($env as $name => $value) {
                $_ENV[$name] = $value;
            }
        }
        if ($rootPackage !== null) {
            self::$RootPackage = $rootPackage;
        }
        $this->App->oneCommand(TestOptions::class);
        if ($output !== null) {
            $this->expectOutputString($output);
        }
        $this->assertSame($exitStatus, $this->App->run()->getLastExitStatus());
        if ($consoleMessages !== null) {
            $this->assertSameConsoleMessages($consoleMessages, $this->ConsoleTarget->getMessages());
        }
        if ($callback) {
            /** @var TestOptions */
            $command = $this->App->getLastCommand();
            $callback($command);
        }
    }

    /**
     * @return array<array{string|null,int,2?:string[],3?:array<array{Level::*,string,2?:array<string,mixed>}>|null,4?:array<string,string>|null,5?:(Closure(TestOptions): mixed)|null,6?:array<string,mixed>|null}>
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
                ['required' => 'start'],
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
                ['required' => 'start'],
            ],
            'synopsis (positional)' => [
                null,
                1,
                [],
                [
                    [Level::ERROR, 'Error: INPUT-FILE required'],
                    [Level::ERROR, 'Error: <endpoint-uri> required'],
                    [Level::INFO, <<<'EOF'

app [-fF] [--nullable] [-v <entity>] [-V <value>,...] [-s <date>]
    [-r[<pattern>]] [--] <INPUT-FILE> <endpoint-uri> [<key>=<VALUE>...]

See 'app --help' for more information.
EOF],
                ],
                ['positional' => '1'],
            ],
            'help (positional)' => [
                null,
                0,
                ['--help'],
                [
                    [Level::INFO, <<<'EOF'
NAME
    app - Test CliCommand options

SYNOPSIS
    app [-fF] [--nullable] [-v entity] [-V value,...] [-s date] [-r[pattern]]
        [--] INPUT-FILE endpoint-uri [key=VALUE...]

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

    INPUT-FILE
        required() VALUE_POSITIONAL with valueType FILE and valueName
        "INPUT_FILE"

    endpoint-uri
        required() VALUE_POSITIONAL with valueName "endpoint_uri"

    key=VALUE...
        VALUE_POSITIONAL with multipleAllowed() and valueName "<key>=<VALUE>"
EOF],
                ],
                ['positional' => '1'],
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
                ['required' => 'start'],
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
                    '--data=' . Json::encode(['filter-regex' => true]),
                    '--print=options,schemaOptions',
                ],
                [],
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
                    '--data=' . Json::encode(['filter-regex' => '/\.php/']),
                    '--print=options,schemaOptions',
                ],
                [],
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
                    '--data=' . Json::encode(['filterRegex' => true]),
                    '--print=options,schemaOptions',
                ],
                [],
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
                    '--data=' . Json::encode(['filterRegex' => '/\.php/']),
                    '--print=options,schemaOptions',
                ],
                [],
            ],
            'get running command' => [
                null,
                0,
                [
                    '--action=get-running-command',
                ],
                [],
                null,
                fn(TestOptions $command) =>
                    self::assertSame($command, $command->Result),
            ],
            'apply option values (invalid values)' => [
                null,
                1,
                [
                    '--action=apply-values',
                    '--data=' . Json::encode(['value' => new stdClass()]),
                    '--data-as-object',
                ],
                [
                    [Level::ERROR, 'Error: Invalid option values'],
                    [Level::INFO, <<<'EOF'

app [-fF] [--nullable] [-v <entity>] [-V <value>,...] [-s <date>]
    [-r[<pattern>]]

See 'app --help' for more information.
EOF],
                ],
            ],
            'version' => [
                null,
                0,
                ['--version'],
                [
                    [Level::INFO, 'app v1.0.0 (deadbeef) PHP ' . \PHP_VERSION],
                ],
            ],
            'version (dev)' => [
                null,
                0,
                ['--version'],
                [
                    [Level::INFO, 'app dev-main (deadbeef) PHP ' . \PHP_VERSION],
                ],
                null,
                null,
                self::DEV_PACKAGE,
            ],
            'version (null ref)' => [
                null,
                0,
                ['--version'],
                [
                    [Level::INFO, 'app dev-main PHP ' . \PHP_VERSION],
                ],
                null,
                null,
                self::NULL_REF_PACKAGE,
            ],
            'markdown (positional, extra)' => [
                <<<'EOF'
## NAME

app - Test CliCommand options

## SYNOPSIS

**`app`** \[**`-fF`**] \[**`--nullable`**] \[**`-v`** *<u>entity</u>*]
\[**`-V`** *<u>value</u>*,...] \[**`-s`** *<u>date</u>*]
\[**`-r`**\[*<u>pattern</u>*]] \
    \[**`--extra-flag`**] \[**`--extra-value`** *<u>EXTRA-VALUE</u>*]
\[**`--`**] *<u>INPUT-FILE</u>* *<u>endpoint-uri</u>* \
    \[*<u>key</u>*=*<u>VALUE</u>*...]

## OPTIONS

- **`-f`**, **`--flag`**

  Flag

- **`-F`**, **`--flags`**

  Flag with multipleAllowed()

- **`--nullable`**

  Flag with nullable() and no short form

- **`-v`**, **`--value`** *<u>entity</u>*

  Value with defaultValue() and valueName *<u>entity</u>*

  The default entity is: `foo`

- **`-V`**, **`--values`** *<u>value</u>*,...

  Value with multipleAllowed(), unique() and nullable()

- **`-s`**, **`--start`** *<u>date</u>*

  Value with conditional required(), valueType DATE and valueName *<u>date</u>*

- **`-r`**, **`--filter-regex`**\[=*<u>pattern</u>*]

  VALUE_OPTIONAL with valueName *<u>pattern</u>* and a default value

  The default pattern is: `/./`

- **`--extra-flag`**

- **`--extra-value`** *<u>EXTRA-VALUE</u>*

- *<u>INPUT-FILE</u>*

  required() VALUE_POSITIONAL with valueType FILE and valueName "INPUT_FILE"

- *<u>endpoint-uri</u>*

  required() VALUE_POSITIONAL with valueName "endpoint_uri"

- *<u>key</u>*=*<u>VALUE</u>*...

  VALUE_POSITIONAL with multipleAllowed() and valueName "\<key>=\<VALUE>"

EOF,
                0,
                ['_md'],
                null,
                ['positional' => '1', 'extra' => '1'],
            ],
            'markdown (positional, extra, collapsed synopsis)' => [
                <<<'EOF'
## NAME

app - Test CliCommand options

## SYNOPSIS

**`app`** \[*<u>options</u>*] \[**`--`**] *<u>INPUT-FILE</u>*
*<u>endpoint-uri</u>* \[*<u>key</u>*=*<u>VALUE</u>*...]

## OPTIONS

- **`-f`**, **`--flag`**

  Flag

- **`-F`**, **`--flags`**

  Flag with multipleAllowed()

- **`--nullable`**

  Flag with nullable() and no short form

- **`-v`**, **`--value`** *<u>entity</u>*

  Value with defaultValue() and valueName *<u>entity</u>*

  The default entity is: `foo`

- **`-V`**, **`--values`** *<u>value</u>*,...

  Value with multipleAllowed(), unique() and nullable()

- **`-s`**, **`--start`** *<u>date</u>*

  Value with conditional required(), valueType DATE and valueName *<u>date</u>*

- **`-r`**, **`--filter-regex`**\[=*<u>pattern</u>*]

  VALUE_OPTIONAL with valueName *<u>pattern</u>* and a default value

  The default pattern is: `/./`

- **`--extra-flag`**

- **`--extra-value`** *<u>EXTRA-VALUE</u>*

- *<u>INPUT-FILE</u>*

  required() VALUE_POSITIONAL with valueType FILE and valueName "INPUT_FILE"

- *<u>endpoint-uri</u>*

  required() VALUE_POSITIONAL with valueName "endpoint_uri"

- *<u>key</u>*=*<u>VALUE</u>*...

  VALUE_POSITIONAL with multipleAllowed() and valueName "\<key>=\<VALUE>"

EOF,
                0,
                ['_md', '1'],
                null,
                ['positional' => '1', 'extra' => '1'],
            ],
            'man page (positional, extra)' => [
                <<<'EOF'
% APP(1) v1.0.0 | app Documentation

# NAME

app - Test CliCommand options

# SYNOPSIS

| **`app`** \[**`-fF`**] \[**`--nullable`**] \[**`-v`** *entity*]
  \[**`-V`** *value*,...] \[**`-s`** *date*] \[**`-r`**\[*pattern*]]
|     \[**`--extra-flag`**] \[**`--extra-value`** *EXTRA-VALUE*] \[**`--`**]
  *INPUT-FILE* *endpoint-uri*
|     \[*key*=*VALUE*...]

# OPTIONS

**`-f`**, **`--flag`**

:   Flag

**`-F`**, **`--flags`**

:   Flag with multipleAllowed()

**`--nullable`**

:   Flag with nullable() and no short form

**`-v`**, **`--value`** *entity*

:   Value with defaultValue() and valueName *entity*

    The default entity is: foo

**`-V`**, **`--values`** *value*,...

:   Value with multipleAllowed(), unique() and nullable()

**`-s`**, **`--start`** *date*

:   Value with conditional required(), valueType DATE and valueName *date*

**`-r`**, **`--filter-regex`**\[=*pattern*]

:   VALUE_OPTIONAL with valueName *pattern* and a default value

    The default pattern is: /./

**`--extra-flag`**

**`--extra-value`** *EXTRA-VALUE*

*INPUT-FILE*

:   required() VALUE_POSITIONAL with valueType FILE and valueName "INPUT_FILE"

*endpoint-uri*

:   required() VALUE_POSITIONAL with valueName "endpoint_uri"

*key*=*VALUE*...

:   VALUE_POSITIONAL with multipleAllowed() and valueName "\<key>=\<VALUE>"

EOF,
                0,
                ['_man'],
                null,
                ['positional' => '1', 'extra' => '1'],
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

    public function testConstructorParameters(): void
    {
        $params = Arr::toMap(
            (new ReflectionMethod(CliApplication::class, '__construct'))->getParameters(),
            'name',
        );
        $appParams = (new ReflectionMethod(Application::class, '__construct'))->getParameters();
        foreach ($appParams as $appParam) {
            $message = sprintf(
                '%s::__construct() parameter does not match %s::__construct(): $%s',
                CliApplication::class,
                Application::class,
                $appParam->name,
            );
            $this->assertArrayHasKey($appParam->name, $params, $message);
            $param = $params[$appParam->name];
            $this->assertSame(
                Arr::unwrap(Reflect::getTypeNames($appParam->getType())),
                Arr::unwrap(Reflect::getTypeNames($param->getType())),
                $message,
            );
            $this->assertSame($appParam->allowsNull(), $param->allowsNull(), $message);
        }
    }
}
