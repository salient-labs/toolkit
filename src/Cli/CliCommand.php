<?php declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Cli\CliApplication;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionBuilder;
use Lkrms\Cli\Concern\HasCliApplication;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\Exception\CliUnknownValueException;
use Lkrms\Console\ConsoleFormatter;
use Lkrms\Contract\ReturnsContainer;
use Lkrms\Facade\Composer;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Base class for CLI commands
 *
 * @implements ReturnsContainer<CliApplication>
 */
abstract class CliCommand implements ReturnsContainer
{
    use HasCliApplication;

    /**
     * Get a one-line description of the command
     *
     */
    abstract public function getShortDescription(): string;

    /**
     * Get a list of options for the command
     *
     * Example:
     *
     * ```php
     * return [
     *     CliOption::build()
     *         ->long("dest")
     *         ->short("d")
     *         ->valueName("DIR")
     *         ->description("Sync files to DIR")
     *         ->optionType(CliOptionType::VALUE)
     *         ->required(true)
     *         ->go(),
     * ];
     * ```
     *
     * @return array<CliOption|CliOptionBuilder>
     */
    abstract protected function getOptionList(): array;

    /**
     * Get a detailed description of the command
     *
     */
    abstract public function getLongDescription(): ?string;

    /**
     * Get content for the command's usage information / help messages
     *
     * `NAME`, `SYNOPSIS`, `OPTIONS` and `DESCRIPTION` are generated
     * automatically and will be ignored if returned by this method.
     *
     * @return array<string,string>|null An array that maps
     * {@see \Lkrms\Cli\Catalog\CliUsageSectionName} values to content, e.g.:
     *
     * ```php
     * [
     *   CliUsageSectionName::EXIT_STATUS => <<<EOF
     * _0_   Command succeeded  
     * _1_   Invalid arguments  
     * _2_   Empty result set  
     * _15_  Operational error
     * EOF,
     * ];
     * ```
     */
    abstract public function getUsageSections(): ?array;

    /**
     * Run the command
     *
     * The command's return value will be:
     * 1. the return value of this method (if an `int` is returned)
     * 2. the last value passed to {@see CliCommand::setExitStatus()}, or
     * 3. `0`, indicating success
     *
     * @param string ...$args Non-option arguments passed to the command.
     * @return int|void
     */
    abstract protected function run(string ...$args);

    /**
     * @var string[]|null
     */
    private $Name;

    /**
     * @var array<string,CliOption>|null
     */
    private $Options;

    /**
     * @var array<string,CliOption>
     */
    private $OptionsByName = [];

    /**
     * @var array<string,CliOption>
     */
    private $PositionalOptions = [];

    /**
     * @var string[]|null
     */
    private $Arguments;

    /**
     * @var array<string,mixed>|null
     */
    private $OptionValues;

    /**
     * @var string[]
     */
    private $OptionErrors = [];

    /**
     * @var string[]
     */
    private $DeferredOptionErrors = [];

    /**
     * @var int|null
     */
    private $NextArgumentIndex;

    /**
     * @var bool
     */
    private $IsHelp;

    /**
     * @var bool
     */
    private $IsVersion;

    /**
     * @var bool
     */
    private $IsRunning = false;

    /**
     * @var int
     */
    private $ExitStatus = 0;

    /**
     * @var int
     */
    private $Runs = 0;

    /**
     * @internal
     * @param string[] $name
     */
    final public function setName(array $name): void
    {
        if (!is_null($this->Name)) {
            throw new LogicException('Name already set');
        }

        $this->Name = $name;
    }

    /**
     * Get the command name as a string of space-delimited subcommands
     *
     * @return string
     */
    final public function getName(): string
    {
        return implode(' ', $this->getNameParts());
    }

    /**
     * Get the command name, including the name used to run the script, as a
     * string of space-delimited subcommands
     *
     * @return string
     */
    final public function getNameWithProgram(): string
    {
        $name = $this->getNameParts();
        array_unshift($name, $this->app()->getProgramName());

        return implode(' ', $name);
    }

    /**
     * Get the command name as an array of subcommands
     *
     * @return string[]
     */
    final public function getNameParts(): array
    {
        return $this->Name ?: [];
    }

    /**
     * @return $this
     */
    private function addOption(CliOption $option)
    {
        try {
            $option->validate();
        } catch (CliUnknownValueException $ex) {
            $this->deferOptionError($ex->getMessage());
        }

        $names = $option->getNames();

        if (array_intersect_key(
            array_flip($names),
            $this->OptionsByName
        )) {
            throw new LogicException('Option names must be unique: ' . implode(', ', $names));
        }

        if ($option->IsPositional) {
            if ($option->Required &&
                    array_filter(
                        $this->PositionalOptions,
                        fn(CliOption $opt) =>
                            !$opt->Required && !$opt->MultipleAllowed
                    )) {
                throw new LogicException('Required positional options must be added before optional ones');
            }
            if (!$option->Required &&
                    array_filter(
                        $this->PositionalOptions,
                        fn(CliOption $opt) =>
                            $opt->MultipleAllowed
                    )) {
                throw new LogicException("'multipleAllowed' positional options must be added after optional ones");
            }
            if ($option->MultipleAllowed &&
                    array_filter(
                        $this->PositionalOptions,
                        fn(CliOption $opt) =>
                            $opt->MultipleAllowed
                    )) {
                throw new LogicException("'multipleAllowed' cannot be set on more than one positional option");
            }

            $this->PositionalOptions[$option->Key] = $option;
        }

        $this->Options[$option->Key] = $option;

        foreach ($names as $name) {
            $this->OptionsByName[$name] = $option;
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function loadOptions()
    {
        if (!is_null($this->Options)) {
            return $this;
        }

        try {
            foreach ($this->getOptionList() as $option) {
                $this->addOption(CliOption::resolve($option));
            }

            return $this->maybeAddHiddenOption('help', 'h')
                        ->maybeAddHiddenOption('version', 'v');
        } catch (Throwable $ex) {
            $this->Options = null;
            $this->OptionsByName = [];
            $this->PositionalOptions = [];
            $this->DeferredOptionErrors = [];

            throw $ex;
        }
    }

    /**
     * @return $this
     */
    private function maybeAddHiddenOption(string $long, string $short)
    {
        if (array_key_exists($long, $this->OptionsByName)) {
            return $this;
        }

        return $this->addOption(CliOption::build()
            ->long($long)
            ->short(array_key_exists($short, $this->OptionsByName) ? null : $short)
            ->hide()
            ->go());
    }

    /**
     *
     * @return CliOption[]
     */
    final public function getOptions(): array
    {
        return
            array_values($this->loadOptions()
                              ->Options);
    }

    final public function hasOption(string $name): bool
    {
        return
            array_key_exists($name, $this->loadOptions()
                                         ->OptionsByName);
    }

    final public function getOption(string $name): ?CliOption
    {
        return
            $this->loadOptions()
                 ->OptionsByName[$name] ?? null;
    }

    final public function getSynopsis(bool $withMarkup = true): string
    {
        $tag = $withMarkup ? '__' : '';

        return
            implode(' ', array_filter([
                $tag . $this->getNameWithProgram() . $tag,
                $this->getOptionsSynopsis($withMarkup),
            ]));
    }

    final public function getSubcommandSynopsis(bool $withMarkup = true): string
    {
        $name = $this->getNameParts();
        $name = array_pop($name);
        $name = $name && $withMarkup ? "__{$name}__" : $name;

        return
            implode(' ', array_filter([
                $name ?: '',
                $this->getOptionsSynopsis(),
            ]));
    }

    final public function getOptionsSynopsis(bool $withMarkup = true): string
    {
        $tag = $withMarkup ? '__' : '';

        // Produce this:
        //
        //     [-ny] [--verbose] [--exclude PATTERN] --from SOURCE DEST
        //
        // By generating this:
        //
        //     $shortFlag  = ['n', 'y'];
        //     $longFlag   = ['verbose'];
        //     $optional   = ['--exclude PATTERN'];
        //     $required   = ['--from SOURCE'];
        //     $positional = ['DEST'];
        //
        $shortFlag = [];
        $longFlag = [];
        $optional = [];
        $required = [];
        $positional = [];

        foreach ($this->getOptions() as $option) {
            if ($option->Hide) {
                continue;
            }

            if ($option->IsFlag) {
                if ($option->Short) {
                    $shortFlag[] = $option->Short;
                    continue;
                }
                $longFlag[] = $option->Long;
                continue;
            }

            $valueName = $option->getFriendlyValueName();

            if ($option->IsPositional) {
                $valueName .= $option->MultipleAllowed ? '...' : '';
                $positional[] = $option->Required ? "$valueName" : "[$valueName]";
                continue;
            }

            $valueName .= $option->MultipleAllowed && $option->Delimiter ? "{$option->Delimiter}..." : '';

            $valueName = $option->Short
                ? "{$tag}-{$option->Short}{$tag}" . ($option->ValueRequired ? " $valueName" : "[$valueName]")
                : "{$tag}--{$option->Long}{$tag}" . ($option->ValueRequired ? " $valueName" : "[=$valueName]");

            if ($option->Required) {
                $required[] = $valueName;
            } else {
                $optional[] = $valueName;
            }
        }

        return implode(' ', array_filter([
            $shortFlag ? "[$tag-" . implode('', $shortFlag) . "$tag]" : '',
            $longFlag ? "[$tag--" . implode("$tag] [$tag--", $longFlag) . "$tag]" : '',
            $optional ? '[' . implode('] [', $optional) . ']' : '',
            $required ? implode(' ', $required) : '',
            $positional ? implode(' ', $positional) : '',
        ]));
    }

    final public function getUsage(bool $oneline = false): string
    {
        $options = '';

        $shortFlag = [];
        $longFlag = [];
        $optional = [];
        $required = [];
        $positional = [];

        foreach ($this->getOptions() as $option) {
            if ($option->Hide) {
                continue;
            }

            $short = $option->Short;
            $long = $option->Long;
            $line = [];
            $value = [];
            $valueName = '';
            $list = '';

            if ($option->IsFlag) {
                if ($short) {
                    $line[] = "__-{$short}__";
                    $shortFlag[] = $short;
                }
                if ($long) {
                    $line[] = "__--{$long}__";
                    if (!$short) {
                        $longFlag[] = $long;
                    }
                }
            } else {
                $valueName = $option->getFriendlyValueName();
                if ($option->IsPositional) {
                    $list = $option->MultipleAllowed ? '...' : '';
                    $line[] = $valueName . $list;
                    $positional[] = $option->Required ? "$valueName$list" : "[$valueName$list]";
                } else {
                    if ($option->MultipleAllowed && $option->Delimiter) {
                        $list = "{$option->Delimiter}...";
                    }
                    if ($short) {
                        $line[] = "__-{$short}__";
                        $value[] = $option->ValueRequired ? " $valueName$list" : "[$valueName$list]";
                    }
                    if ($long) {
                        $line[] = "__--{$long}__";
                        $value[] = $option->ValueRequired ? " $valueName$list" : "[=$valueName$list]";
                    }
                    if ($option->Required) {
                        $required[] = $line[0] . $value[0];
                    } else {
                        $optional[] = $line[0] . $value[0];
                    }
                }
            }

            if ($oneline) {
                continue;
            }

            // Format:                          Or:
            //
            //     _-o, --option_[=__VALUE__]       _-o, --option_[=__VALUE__]
            //       Option description               __Default:__ ___auto___
            //         __Default:__ ___auto___        __Values:__
            //         __Values:__                    - _option1_
            //         - _option1_                    - _option2_
            //         - _option2_                    - _option3_
            //         - _option3_
            //
            $line = implode(', ', $line);
            $value = array_pop($value) ?: '';
            $options .= "\n$line$value";

            $optionLines = [];
            $indent = '    ';

            if ($description = trim($option->Description)) {
                $optionLines[] = $this->prepareUsage($description, $indent);
            }

            $generatedLines = [];
            $compact = true;
            if ($option->AllowedValues) {
                $allowedValues = array_map(
                    fn(string $value): string => ConsoleFormatter::escape($value, true),
                    $option->AllowedValues
                );
                $start = "{$indent}__Values:__";
                if (mb_strlen(implode(' ', $option->AllowedValues)) < 78 - mb_strlen($start)) {
                    $generatedLines[] = "$start _" . implode('_ _', $allowedValues) . '_';
                } else {
                    $generatedLines[] =
                        "$start  \n$indent" . implode(
                            "  \n$indent",
                            array_map(
                                fn(string $value): string => sprintf('- _%s_', $value),
                                $allowedValues
                            )
                        );
                    $compact = false;
                }
            }

            if ($option->DefaultValue) {
                $defaultValue = array_map(
                    fn(string $value): string => ConsoleFormatter::escape($value, true),
                    (array) $option->DefaultValue
                );
                $generatedLines[] = sprintf(
                    '%s__Default:__ _%s_',
                    $indent,
                    implode(
                        sprintf('_%s_', $option->Delimiter ?: ' '),
                        $defaultValue
                    )
                );
            }

            if ($generatedLines) {
                $optionLines[] = implode($compact ? "\n" : "\n\n", $generatedLines);
            }
            $options .=
                ($optionLines
                    ? "\n" . implode("\n\n", $optionLines)
                    : '') . "\n";
        }

        $synopsis = ($shortFlag ? ' [__-' . implode('', $shortFlag) . '__]' : '')
            . ($longFlag ? ' [__--' . implode('__] [__--', $longFlag) . '__]' : '')
            . ($optional ? ' [' . implode('] [', $optional) . ']' : '')
            . ($required ? ' ' . implode(' ', $required) : '')
            . ($positional ? ' ' . implode(' ', $positional) : '');

        if ($oneline) {
            return $synopsis;
        }

        $name = $this->getNameWithProgram();
        if ($sections = $this->getUsageSections() ?: []) {
            $sections = array_map(
                fn(string $section) => $this->prepareUsage($section),
                $sections
            );
        }

        $sections = [
            'NAME' => $name . ' - ' . $this->getShortDescription(),
            'SYNOPSIS' => '__' . $name . '__' . $synopsis,
            'OPTIONS' => trim($options),
            'DESCRIPTION' => $this->prepareUsage($this->getLongDescription()),
        ] + $sections;

        return $this->app()->buildUsageSections($sections);
    }

    private function prepareUsage(?string $description, ?string $indent = null): string
    {
        if (!$description) {
            return '';
        }

        $description = wordwrap(
            str_replace(
                '{{command}}',
                $this->getNameWithProgram(),
                Convert::unwrap($description)
            ),
            76 - ($indent ? strlen($indent) : 0)
        );

        if ($indent) {
            return $indent . str_replace("\n", "\n" . $indent, $description);
        }

        return $description;
    }

    /**
     * Record an option-related error
     *
     * @return $this
     * @phpstan-impure
     */
    private function optionError(string $message)
    {
        $this->OptionErrors[] = $message;

        return $this;
    }

    /**
     * Record an option-related error to report only if the command is running
     *
     * @return $this
     * @phpstan-impure
     */
    private function deferOptionError(string $message)
    {
        $this->DeferredOptionErrors[] = $message;

        return $this;
    }

    /**
     * @return $this
     */
    private function loadOptionValues()
    {
        if (!is_null($this->OptionValues)) {
            return $this;
        }

        $this->OptionErrors = [];
        $this->loadOptions();
        $this->NextArgumentIndex = null;
        $this->IsHelp = false;
        $this->IsVersion = false;

        $args = $this->Arguments;
        $merged = [];

        for ($i = 0; $i < count($args); $i++) {
            list($arg, $short, $matches) = [$args[$i], false, null];

            if (preg_match('/^-([0-9a-z])(.*)/i', $arg, $matches)) {
                $name = $matches[1];
                $value = $matches[2] ?: null;
                $short = true;
            } elseif (preg_match('/^--([0-9a-z_-]+)(=(.*))?$/i', $arg, $matches)) {
                $name = $matches[1];
                $value = ($matches[2] ?? null) ? $matches[3] : null;
            } else {
                if ($arg === '--') {
                    $i++;
                } elseif (substr($arg, 0, 1) === '-') {
                    $this->optionError("invalid argument '$arg'");

                    continue;
                }

                break;
            }

            $option = $this->OptionsByName[$name] ?? null;
            $isDefault = false;

            if (is_null($option) || $option->IsPositional) {
                $this->optionError("unknown option '$name'");

                continue;
            } elseif ($option->IsFlag) {
                // Handle multiple short flags per argument, e.g. `cp -rv`
                if ($short && $value) {
                    $args[$i] = "-$value";
                    $i--;
                }

                $value = true;
            } elseif (!$option->ValueRequired) {
                // Don't use the default value if `--option=` was given
                if (is_null($value) && !is_null($option->DefaultValue)) {
                    $value = $option->DefaultValue;
                    $isDefault = true;
                }
            } elseif (is_null($value)) {
                $i++;
                if (is_null($value = $args[$i] ?? null)) {
                    // Allow null to be stored to prevent an additional
                    // "argument required" error
                    $this->optionError(
                        "{$option->DisplayName} requires a value" . $option->maybeGetAllowedValues()
                    );
                    $i--;
                }
            }

            $key = $option->Key;

            if ($option->MultipleAllowed && !$option->IsFlag) {
                // Interpret "--option=" as "clear previous --option values"
                if ($option->ValueRequired && $value === '') {
                    $merged[$key] = [];
                    continue;
                }
                $value = $option->maybeSplitValue($value);
                if (!$isDefault && ($option->KeepDefault || $option->KeepEnv) && !array_key_exists($key, $merged)) {
                    $value = array_merge($option->DefaultValue, $value);
                }
            }

            if (isset($merged[$key])) {
                $merged[$key] = array_merge((array) $merged[$key], (array) $value);
            } else {
                $merged[$key] = $value;
            }
        }

        $pending = count($this->PositionalOptions);
        foreach ($this->PositionalOptions as $option) {
            if (!($i < count($args))) {
                break;
            }
            $pending--;
            if ($option->Required || !$option->MultipleAllowed) {
                $merged[$option->Key] = $option->MultipleAllowed ? [$args[$i++]] : $args[$i++];
                if (!$option->MultipleAllowed) {
                    continue;
                }
            }
            while (count($args) - $i - $pending > 0) {
                $merged[$option->Key][] = $args[$i++];
            }
        }

        $this->NextArgumentIndex = $i;

        /**
         * @var string|string[]|bool|int|null $value
         */
        foreach ($merged as $key => &$value) {
            $option = $this->Options[$key];

            if ($option->Long === 'help') {
                $this->IsHelp = true;
                continue;
            }

            if ($option->Long === 'version') {
                $this->IsVersion = true;
                continue;
            }

            if (!$option->MultipleAllowed && is_array($value)) {
                $this->optionError("{$option->DisplayName} cannot be used multiple times");
            }

            if ($option->AllowedValues && !is_null($value)) {
                try {
                    $value = $option->applyUnknownValuePolicy($value);
                } catch (CliUnknownValueException $ex) {
                    $this->optionError($ex->getMessage());
                }
            }
        }
        unset($value);

        foreach ($this->Options as $option) {
            if ($option->Required &&
                (!array_key_exists($option->Key, $merged) ||
                    $merged[$option->Key] === [])) {
                if (!(count($args) === 1 && ($this->IsHelp || $this->IsVersion))) {
                    $this->optionError(
                        "{$option->DisplayName} required" . $option->maybeGetAllowedValues()
                    );;
                }

                continue;
            }

            $value = $merged[$option->Key] ?? ($option->ValueRequired ? $option->DefaultValue : null);

            if ($option->AddAll && !is_null($value) && in_array('ALL', (array) $value)) {
                $value = array_diff($option->AllowedValues, ['ALL']);
            } elseif ($option->IsFlag && $option->MultipleAllowed) {
                $value = count((array) $value);
            } elseif ($option->MultipleAllowed) {
                $value = (array) $value;
            }
            $value = $option->applyValue($value);
            if ($value !== null) {
                $this->OptionValues[$option->Key] = $value;
            }
        }

        if ($this->OptionErrors) {
            throw new CliInvalidArgumentsException(
                ...$this->OptionErrors,
                ...$this->DeferredOptionErrors
            );
        }

        return $this;
    }

    /**
     * Get the value of a command line option
     *
     * For values that can be given multiple times, an array of values will be
     * returned. For flags that can be given multiple times, the number of uses
     * will be returned.
     *
     * @param string $name Either the `Short` or `Long` name of the option
     * @return string|string[]|bool|int|null
     */
    final public function getOptionValue(string $name)
    {
        $this->assertHasRun();

        if (!($option = $this->getOption($name))) {
            throw new LogicException("No option with name '$name'");
        }

        return $this->OptionValues[$option->Key] ?? null;
    }

    /**
     * @return array<string,string|string[]|bool|int|null>
     */
    final public function getOptionValues(): array
    {
        $this->assertHasRun();

        $values = [];
        foreach ($this->Options as $option) {
            $name = $option->Long ?: $option->Short;
            $values[$name] = $this->OptionValues[$option->Key] ?? null;
        }

        return $values;
    }

    /**
     * Get the value of an option in its command line form
     *
     * Returns `null` if the option is unset, otherwise returns its current
     * value as it would be given on the command line.
     *
     * Set `$value` to override the option's current value.
     *
     * @internal
     * @param string|CliOption $option
     * @param string|string[]|bool|int|null $value
     */
    final public function getEffectiveArgument($option, bool $shellEscape = false, $value = null): ?string
    {
        $this->assertHasRun();
        if (is_string($option)) {
            if (!($option = $this->getOption($option))) {
                throw new LogicException("No option with name '$option'");
            }
        } elseif ($this->Options[$option->Key] !== $option) {
            throw new LogicException('No matching option');
        }

        if (func_num_args() < 3) {
            $value = $this->OptionValues[$option->Key] ?? null;
        }

        if (is_null($value) || $value === [] || $value === false || $value === 0) {
            return null;
        }

        if (is_int($value)) {
            if ($option->Short) {
                return '-' . str_repeat($option->Short, $value);
            }

            return implode(' ', array_fill(0, $value, "--{$option->Long}"));
        }

        if (is_array($value)) {
            $value = implode(',', $value);
        }
        if ($shellEscape && is_string($value)) {
            $value = Convert::toShellArg($value);
        }

        return $option->IsPositional
            ? $value
            : ($option->Long
                ? "--{$option->Long}" . (is_string($value) ? "=$value" : '')
                : "-{$option->Short}" . $value);
    }

    /**
     * Get a command line that would unambiguously repeat the current invocation
     *
     * @internal
     * @param array<string,string|string[]|bool|int|null> $values
     * @return string[]
     */
    final public function getEffectiveCommandLine(bool $shellEscape = false, array $values = []): array
    {
        $this->assertHasRun();

        $args = $this->getNameParts();
        array_unshift($args, $this->app()->getProgramName());
        foreach ($this->Options as $option) {
            $name = null;
            foreach (array_filter([$option->Long, $option->Short]) as $key) {
                if (array_key_exists($key, $values)) {
                    $name = $key;
                    break;
                }
            }

            $arg = $name
                ? $this->getEffectiveArgument($option, $shellEscape, $values[$name])
                : $this->getEffectiveArgument($option, $shellEscape);

            if ($option->IsPositional) {
                $positional[] = $arg;
                continue;
            }
            $args[] = $arg;
        }
        array_push($args, ...($positional ?? []));

        return array_values(array_filter($args, fn($arg) => !is_null($arg)));
    }

    /**
     * @return $this
     */
    private function assertHasRun()
    {
        if (is_null($this->OptionValues)) {
            throw new RuntimeException('Command must be invoked first');
        }

        return $this;
    }

    /**
     * Parse the arguments and run the command
     *
     * @param string[] $args
     * @see CliCommand::run()
     */
    final public function __invoke(array $args): int
    {
        $this->Arguments = $args;
        $this->OptionValues = null;
        $this->ExitStatus = 0;
        $this->Runs++;

        $this->loadOptionValues();

        if ($this->IsHelp) {
            Console::out($this->getUsage());

            return 0;
        }

        if ($this->IsVersion) {
            $appName = $this->app()->getAppName();
            $version = Composer::getRootPackageVersion(true, true);
            Console::out("__{$appName}__ $version");

            return 0;
        }

        if ($this->DeferredOptionErrors) {
            throw new CliInvalidArgumentsException(
                ...$this->DeferredOptionErrors
            );
        }

        $this->IsRunning = true;
        try {
            $return = $this->run(...array_slice($this->Arguments, $this->NextArgumentIndex));
        } finally {
            $this->IsRunning = false;
        }

        if (is_int($return)) {
            return $return;
        }

        return $this->ExitStatus;
    }

    /**
     * True if the command is currently running
     *
     */
    final protected function isRunning(): bool
    {
        return $this->IsRunning;
    }

    /**
     * Set the command's return value / exit status
     *
     * @return $this
     * @see CliCommand::run()
     */
    final protected function setExitStatus(int $status)
    {
        $this->ExitStatus = $status;

        return $this;
    }

    /**
     * Get the current return value / exit status
     *
     * @see CliCommand::setExitStatus()
     * @see CliCommand::run()
     */
    final protected function getExitStatus(): int
    {
        return $this->ExitStatus;
    }

    /**
     * Get the number of times the command has run, including the current run
     * (if applicable)
     *
     */
    final protected function getRuns(): int
    {
        return $this->Runs;
    }
}
