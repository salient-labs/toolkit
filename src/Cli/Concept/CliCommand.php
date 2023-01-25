<?php declare(strict_types=1);

namespace Lkrms\Cli\Concept;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionBuilder;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Concern\HasCliAppContainer;
use Lkrms\Contract\ReturnsContainer;
use Lkrms\Facade\Composer;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use RuntimeException;
use UnexpectedValueException;

/**
 * Base class for CLI commands
 *
 */
abstract class CliCommand implements ReturnsContainer
{
    use HasCliAppContainer;

    /**
     * Get a one-line description of the command
     *
     */
    abstract public function getShortDescription(): string;

    /**
     * Return a list of options for the command
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
     * Get content for the command's usage information / help page
     *
     * `NAME`, `SYNOPSIS`, `OPTIONS` and `DESCRIPTION` are generated
     * automatically and will be ignored if returned by this method.
     *
     * @return array<string,string>|null An array that maps
     * {@see \Lkrms\Cli\CliUsageSectionName} values to content, e.g.:
     * ```php
     * [
     *   CliUsageSectionName::EXIT_STATUS => <<<EOF
     *   0  Data was retrieved successfully
     *   1  There was no data in the result set
     *   2  An error occurred
     *   EOF,
     * ];
     * ```
     */
    abstract public function getUsageSections(): ?array;

    /**
     * Run the command
     *
     * PHP's exit status will be:
     * 1. the return value of this method (if an `int` is returned)
     * 2. the last value passed to {@see CliCommand::setExitStatus()}, or
     * 3. `0`, indicating success, unless an unhandled error occurs
     *
     * @param string ...$params
     * @return int|void
     */
    abstract protected function run(string ...$params);

    /**
     * @var string[]|null
     */
    private $Name;

    /**
     * @var CliOption[]|null
     */
    private $Options;

    /**
     * @var array<string,CliOption>
     */
    private $OptionsByName = [];

    /**
     * @var array<string,CliOption>
     */
    private $OptionsByKey = [];

    /**
     * @var array<string,CliOption>
     */
    private $PositionalOptions = [];

    /**
     * @var array<string,CliOption>
     */
    private $HiddenOptions = [];

    /**
     * @var string[]|null
     */
    private $Arguments;

    /**
     * @var array<string,string|array|bool|null>|null
     */
    private $OptionValues;

    /**
     * @var string[]
     */
    private $OptionErrors = [];

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
            throw new RuntimeException('Name already set');
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

    private function addOption(CliOption $option, array &$options, bool $hide = false)
    {
        $this->applyOption($option, true, $options, $hide);
    }

    private function applyOption(CliOption $option, bool $validate = false, ?array &$options = null, bool $hide = false)
    {
        $names = array_filter([$option->Short, $option->Long]);

        if ($validate) {
            $option->validate();

            if (!empty(array_intersect($names, array_keys($this->OptionsByName)))) {
                throw new UnexpectedValueException('Option names must be unique: ' . implode(', ', $names));
            }

            if ($option->IsPositional) {
                if ($option->IsRequired &&
                        !empty(array_filter($this->PositionalOptions, fn(CliOption $opt) => !$opt->IsRequired && !$opt->MultipleAllowed))) {
                    throw new UnexpectedValueException('Required positional options must be added before optional ones');
                }
                if (!$option->IsRequired &&
                        !empty(array_filter($this->PositionalOptions, fn(CliOption $opt) => $opt->MultipleAllowed))) {
                    throw new UnexpectedValueException("'multipleAllowed' positional options must be added after optional ones");
                }
                if ($option->MultipleAllowed &&
                        !empty(array_filter($this->PositionalOptions, fn(CliOption $opt) => $opt->MultipleAllowed))) {
                    throw new UnexpectedValueException("'multipleAllowed' cannot be set on more than one positional option");
                }
            }
        }

        foreach ($names as $key) {
            $this->OptionsByName[$key] = $option;
        }

        $this->OptionsByKey[$option->Key] = $option;

        if ($option->IsPositional) {
            $this->PositionalOptions[$option->Key] = $option;
        }

        if ($hide || array_key_exists($option->Key, $this->HiddenOptions)) {
            $this->HiddenOptions[$option->Key] = $option;
        }

        if (!is_null($options)) {
            $options[] = $option;
        }
    }

    private function loadOptions()
    {
        if (!is_null($this->Options)) {
            return;
        }

        $_options = $this->getOptionList();
        $options  = [];

        foreach ($_options as $option) {
            $this->addOption(CliOption::resolve($option), $options);
        }

        if (!array_key_exists('help', $this->OptionsByName)) {
            $this->addOption(CliOption::build()
                                 ->long('help')
                                 ->short(array_key_exists('h', $this->OptionsByName) ? null : 'h')
                                 ->go(), $options, true);
        }

        if (!array_key_exists('version', $this->OptionsByName)) {
            $this->addOption(CliOption::build()
                                 ->long('version')
                                 ->short(array_key_exists('v', $this->OptionsByName) ? null : 'v')
                                 ->go(), $options, true);
        }

        $this->Options = $options;
    }

    /**
     *
     * @return CliOption[]
     */
    final public function getOptions(): array
    {
        $this->loadOptions();

        return $this->Options;
    }

    final public function hasOption(string $name): bool
    {
        $this->loadOptions();

        return !is_null($this->OptionsByName[$name] ?? null);
    }

    final public function getOption(string $name): ?CliOption
    {
        $this->loadOptions();

        return $this->OptionsByName[$name] ?? null;
    }

    final public function getUsage(bool $oneline = false): string
    {
        $options = '';

        // To produce a one-line summary like this:
        //
        //     sync [-ny] [--verbose] [--exclude PATTERN] --from SOURCE DEST
        //
        // Generate values like these:
        //
        //     $shortFlag  = ['n', 'y'];
        //     $longFlag   = ['verbose'];
        //     $optional   = ['--exclude PATTERN'];
        //     $required   = ['--from SOURCE'];
        //     $positional = ['DEST'];
        //
        $shortFlag  = [];
        $longFlag   = [];
        $optional   = [];
        $required   = [];
        $positional = [];

        foreach ($this->getOptions() as $option) {
            if (array_key_exists($option->Key, $this->HiddenOptions)) {
                continue;
            }

            [$short, $long, $line, $value, $valueName, $list] = [
                $option->Short,
                $option->Long,
                [],
                [],
                '',
                '',
            ];

            if ($option->IsFlag) {
                if ($short) {
                    $line[]      = "-{$short}";
                    $shortFlag[] = $short;
                }
                if ($long) {
                    $line[] = "--{$long}";
                    if (!$short) {
                        $longFlag[] = $long;
                    }
                }
            } else {
                $valueName = $option->getFriendlyValueName();
                if ($option->IsPositional) {
                    $list         = $option->MultipleAllowed ? '...' : '';
                    $line[]       = $valueName . $list;
                    $positional[] = $option->IsRequired ? "$valueName$list" : "[$valueName$list]";
                } else {
                    if ($option->MultipleAllowed && $option->Delimiter) {
                        $list = "{$option->Delimiter}...";
                    }
                    if ($short) {
                        $line[]  = "-{$short}";
                        $value[] = $option->IsValueRequired ? " $valueName$list" : "[$valueName$list]";
                    }
                    if ($long) {
                        $line[]  = "--{$long}";
                        $value[] = $option->IsValueRequired ? " $valueName$list" : "[=$valueName$list]";
                    }
                    if ($option->IsRequired) {
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
            $line = '_' . implode(', ', $line) . '_';
            if ($value = array_pop($value) ?: '') {
                $value = str_replace($valueName, '__' . $valueName . '__', $value);
            }
            $options .= "\n$line$value";

            $optionLines = [];
            if ($option->DefaultValue) {
                $optionLines[] = sprintf('__Default:__ ___%s___', implode(',', Convert::toArray($option->DefaultValue)));
            }
            if ($option->AllowedValues) {
                $optionLines[] = '__Values:__';
                array_push($optionLines, ...array_map(fn(string $v): string => sprintf('- _%s_', $v), $option->AllowedValues));
            }
            $optionLines = $optionLines
                ? "\n  " . implode("\n  ", $optionLines)
                : '';

            if ($option->Description) {
                $options    .= "\n  " . $this->prepareDescription($option->Description, '  ', 76);
                // Increase the indentation of "Default:" and "Values:" to
                // separate them from the description
                $optionLines = $optionLines ? str_replace("\n", "\n  ", $optionLines) : '';
            }

            $options .= $optionLines . "\n";
        }

        $synopsis = ($shortFlag ? ' [-' . implode('', $shortFlag) . ']' : '')
            . ($longFlag ? ' [--' . implode('] [--', $longFlag) . ']' : '')
            . ($optional ? ' [' . implode('] [', $optional) . ']' : '')
            . ($required ? ' ' . implode(' ', $required) : '')
            . ($positional ? ' ' . implode(' ', $positional) : '');

        if ($oneline) {
            return $synopsis;
        }

        $name = $this->getNameWithProgram();

        $sections = [
            'NAME'        => '__' . $name . '__ - ' . $this->getShortDescription(),
            'SYNOPSIS'    => '__' . $name . '__' . $synopsis,
            'OPTIONS'     => ltrim($options),
            'DESCRIPTION' => $this->prepareDescription($this->getLongDescription(), '  ', 78),
        ] + ($this->getUsageSections() ?: []);

        return $this->app()->buildUsageSections($sections);
    }

    private function prepareDescription(?string $description, string $indent, int $width): string
    {
        if (!$description) {
            return '';
        }

        $description = str_replace('{{command}}',
                                   $this->getNameWithProgram(),
                                   Convert::unwrap($description));

        return str_replace("\n",
                           "\n" . $indent,
                           wordwrap($description, $width));
    }

    private function optionError(string $message)
    {
        $this->OptionErrors[] = $message;
    }

    private function loadOptionValues()
    {
        if (!is_null($this->OptionValues)) {
            return;
        }

        $this->loadOptions();
        $this->OptionErrors      = [];
        $this->NextArgumentIndex = null;
        $this->IsHelp            = false;
        $this->IsVersion         = false;

        $args   = $this->Arguments;
        $merged = [];

        for ($i = 0; $i < count($args); $i++) {
            list($arg, $short, $matches) = [$args[$i], false, null];

            if (preg_match('/^-([0-9a-z])(.*)/i', $arg, $matches)) {
                $name  = $matches[1];
                $value = $matches[2] ?: null;
                $short = true;
            } elseif (preg_match('/^--([0-9a-z_-]+)(=(.*))?$/i', $arg, $matches)) {
                $name  = $matches[1];
                $value = ($matches[2] ?? null) ? $matches[3] : null;
            } else {
                if ($arg == '--') {
                    $i++;
                } elseif (substr($arg, 0, 1) == '-') {
                    $this->optionError("invalid argument '$arg'");

                    continue;
                }

                break;
            }

            $option = $this->OptionsByName[$name] ?? null;

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
            } elseif (!$option->IsValueRequired) {
                $value = $value ?: $option->DefaultValue ?: '';
            } elseif (is_null($value)) {
                $i++;

                if (is_null($value = ($args[$i] ?? null))) {
                    // Allow null to be stored to prevent an additional
                    // "argument required" error
                    $this->optionError("{$option->DisplayName} requires a value"
                        . $this->maybeGetAllowedValues($option));
                    $i--;
                }
            }

            if ($option->MultipleAllowed &&
                    $option->Delimiter && $value && is_string($value)) {
                $value = explode($option->Delimiter, $value);
            }

            $key = $option->Key;

            if (isset($merged[$key])) {
                $merged[$key] = array_merge(Convert::toArray($merged[$key]), Convert::toArray($value));
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
            if ($option->IsRequired || !$option->MultipleAllowed) {
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

        foreach ($merged as $key => $value) {
            $option = $this->OptionsByKey[$key];

            if ($option->Long == 'help') {
                $this->IsHelp = true;

                continue;
            }

            if ($option->Long == 'version') {
                $this->IsVersion = true;

                continue;
            }

            if (!$option->MultipleAllowed && is_array($value)) {
                $this->optionError("{$option->DisplayName} cannot be used multiple times");
            }

            if (!is_null($option->AllowedValues) && !is_null($value) &&
                    !empty($invalid = array_diff(Convert::toArray($value), $option->AllowedValues))) {
                $this->optionError("invalid {$option->DisplayName} "
                    . Convert::plural(count($invalid), 'value') . " '" . implode("','", $invalid) . "'"
                    . $this->maybeGetAllowedValues($option, ' (expected one? of: {})'));
            }
        }

        foreach ($this->Options as &$option) {
            if ($option->IsRequired && !array_key_exists($option->Key, $merged)) {
                if (!(count($args) == 1 && ($this->IsHelp || $this->IsVersion))) {
                    $this->optionError("{$option->DisplayName} required"
                        . $this->maybeGetAllowedValues($option));;
                }

                continue;
            }

            $value = $merged[$option->Key] ?? (!$option->IsValueRequired ? null : $option->DefaultValue);

            if ($option->IsFlag && $option->MultipleAllowed) {
                $value = count(Convert::toArray($value, true));
            } elseif ($option->MultipleAllowed) {
                $value = Convert::toArray($value, true);
            }

            $option = $option->withValue($value);
            $this->applyOption($option, false);
        }

        if ($this->OptionErrors) {
            throw new CliArgumentsInvalidException($this->OptionErrors);
        }

        $this->OptionValues = $merged;
    }

    private function maybeGetAllowedValues(CliOption $option, string $message = ' (one? of: {})'): string
    {
        if (!$option->AllowedValues) {
            return '';
        }
        $delimiter = ($option->MultipleAllowed ? $option->Delimiter : null) ?: ',';

        return str_replace([
            '?',
            '{}',
        ], [
            $option->MultipleAllowed ? ' or more' : '',
            implode($delimiter, $option->AllowedValues),
        ], $message);
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
            throw new UnexpectedValueException("No option with name '$name'");
        }

        return $option->Value;
    }

    /**
     * @return array<string,string|string[]|bool|int|null>
     */
    final public function getOptionValues(): array
    {
        $this->assertHasRun();

        $values = [];
        foreach ($this->Options as $option) {
            $name          = $option->Long ?: $option->Short;
            $values[$name] = $option->Value;
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
        if (is_string($option)) {
            $this->assertHasRun();

            if (!($option = $this->getOption($option))) {
                throw new UnexpectedValueException("No option with name '$option'");
            }
        }

        if (func_num_args() > 2) {
            $option = $option->withValue($value);
        }

        if (is_null($option->Value) || $option->Value === [] || $option->Value === false || $option->Value === 0) {
            return null;
        }

        if (is_int($option->Value)) {
            if ($option->Short) {
                return '-' . str_repeat($option->Short, $option->Value);
            }

            return implode(' ', array_fill(0, $option->Value, "--{$option->Long}"));
        }

        $value = null;
        if (is_array($option->Value)) {
            $value = implode(',', $option->Value);
        } elseif (is_string($option->Value)) {
            $value = $option->Value;
        }
        if ($shellEscape && !is_null($value)) {
            $value = Convert::toShellArg($value);
        }

        return $option->IsPositional ? $value : ($option->Long
            ? "--{$option->Long}" . (is_null($value) ? '' : "=$value")
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

    private function assertHasRun()
    {
        if (is_null($this->OptionValues)) {
            throw new RuntimeException('Command must be invoked first');
        }
    }

    /**
     * Parse the arguments and run the command
     *
     * @param string[] $args
     * @see CliCommand::run()
     */
    final public function __invoke(array $args): int
    {
        $this->Arguments    = $args;
        $this->OptionValues = null;
        $this->ExitStatus   = 0;
        $this->Runs++;

        $this->loadOptionValues();

        if ($this->IsHelp) {
            Console::out($this->getUsage());

            return 0;
        }

        if ($this->IsVersion) {
            $appName = $this->app()->getAppName();
            $version = Composer::getRootPackageVersion();
            Console::out("__{$appName}__ $version");

            return 0;
        }

        if (is_int($return = $this->run(...array_slice($this->Arguments, $this->NextArgumentIndex)))) {
            return $return;
        }

        return $this->ExitStatus;
    }

    /**
     * Set the command's return value / exit status
     *
     * @see CliCommand::run()
     */
    final protected function setExitStatus(int $status)
    {
        $this->ExitStatus = $status;
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
     *
     */
    final protected function getRuns(): int
    {
        return $this->Runs;
    }
}
