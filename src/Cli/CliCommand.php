<?php declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Cli\Catalog\CliHelpSectionName;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\Catalog\CliOptionVisibility;
use Lkrms\Cli\Contract\ICliApplication;
use Lkrms\Cli\Contract\ICliCommand;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\Exception\CliUnknownValueException;
use Lkrms\Cli\Support\CliHelpStyle;
use Lkrms\Console\Support\ConsoleLoopbackFormat;
use Lkrms\Console\ConsoleFormatter as Formatter;
use Lkrms\Facade\Console;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Package;
use Lkrms\Utility\Str;
use LogicException;
use Throwable;

/**
 * Base class for runnable CLI commands
 */
abstract class CliCommand implements ICliCommand
{
    /**
     * @var ICliApplication
     */
    protected $App;

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
     * @var string[]
     */
    private $DeferredOptionErrors = [];

    /**
     * @var string[]
     */
    private $Arguments = [];

    /**
     * @var array<string,mixed>
     */
    private $ArgumentValues = [];

    /**
     * @var array<string,mixed>|null
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
    private $HasHelpArgument = false;

    /**
     * @var bool
     */
    private $HasVersionArgument = false;

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

    private Formatter $LoopbackFormatter;

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
     */
    abstract protected function getLongDescription(): ?string;

    /**
     * Get content for the command's help message / manual page
     *
     * `NAME`, `SYNOPSIS`, `OPTIONS` and `DESCRIPTION` are generated
     * automatically and will be ignored if returned by this method.
     *
     * @return array<string,string>|null An array that maps
     * {@see CliHelpSectionName} values to content, e.g.:
     *
     * ```php
     * [
     *     CliHelpSectionName::EXIT_STATUS => <<<EOF
     * `{{program}}` returns 0 when the operation succeeds, 1 when invalid arguments
     * are given, and 16 when an unhandled exception is thrown. Other non-zero values
     * may be returned for other failures.
     * EOF,
     * ];
     * ```
     */
    abstract protected function getHelpSections(): ?array;

    /**
     * Override to modify the command's JSON Schema before it is returned
     *
     * @param array{'$schema':string,title:string|null,type:string,required:string[],properties:array<string,mixed>} $schema
     * @return array{'$schema':string,title:string|null,type:string,required:string[],properties:array<string,mixed>,...}
     */
    protected function filterJsonSchema(array $schema): array
    {
        return $schema;
    }

    public function __construct(ICliApplication $app)
    {
        $this->App = $app;
    }

    /**
     * @see CliCommand::run()
     */
    final public function __invoke(string ...$args): int
    {
        $this->reset();

        $this->Arguments = $args;
        $this->Runs++;

        $this->loadOptionValues();

        if ($this->HasHelpArgument) {
            $width = $this->App->getHelpWidth();
            Console::stdout($this->App->buildHelp($this->getHelp(true, $width)));

            return 0;
        }

        if ($this->HasVersionArgument) {
            $appName = $this->App->getAppName();
            $version = Package::version(true, true);
            Console::stdout("__{$appName}__ $version");

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

    final public function app(): ICliApplication
    {
        return $this->App;
    }

    final public function container(): ICliApplication
    {
        return $this->App;
    }

    /**
     * @inheritDoc
     */
    final public function name(): string
    {
        return implode(' ', $this->Name ?? []);
    }

    /**
     * @inheritDoc
     */
    final public function nameParts(): array
    {
        return $this->Name ?: [];
    }

    final public function setName(array $name): void
    {
        if ($this->Name !== null) {
            throw new LogicException('Name already set');
        }

        $this->Name = $name;
    }

    /**
     * @inheritDoc
     */
    final public function getHelp(bool $withMarkup = true, ?int $width = 80, bool $collapse = false): array
    {
        $style = $withMarkup
            ? $this->App->getHelpStyle()
            : new CliHelpStyle();

        $b = $style->Bold;
        $em = $style->Italic;
        $esc = $style->Escape;
        $indent = $style->OptionIndent;
        $beforeSynopsis = $style->OptionPrefix;
        $beforeDescription = $style->OptionDescriptionPrefix;
        $visibility = $style->HelpVisibility;
        $collapse = $collapse || $style->CollapseHelpSynopsis;

        $formatter = $this->getLoopbackFormatter();

        $options = [];
        foreach ($this->getOptions() as $option) {
            if (!($option->Visibility & $visibility)) {
                continue;
            }

            $short = $option->Short;
            $long = $option->Long;
            $line = [];
            $value = [];
            $valueName = $option->IsFlag ? '' : $option->formatValueName();
            $allowed = null;
            $default = null;
            $prefix = '';
            $suffix = '';

            if ($option->IsFlag) {
                if ($short !== null) {
                    $line[] = "{$b}-{$short}{$b}";
                }
                if ($long !== null) {
                    $line[] = "{$b}--{$long}{$b}";
                }
            } else {
                if ($option->AllowedValues) {
                    foreach ($option->AllowedValues as $optionValue) {
                        $allowed[] = $em . $formatter->escapeTags((string) $optionValue) . $em;
                    }
                }

                if ($option->IsPositional) {
                    if ($option->MultipleAllowed) {
                        $line[] = '{}...';
                    } else {
                        $line[] = '{}';
                    }
                } else {
                    $ellipsis = '';
                    if ($option->MultipleAllowed) {
                        if ($option->Delimiter) {
                            $ellipsis = "{$option->Delimiter}...";
                        } elseif ($option->ValueRequired) {
                            $prefix = "{$esc}(";
                            $suffix = ')...';
                        } else {
                            $suffix = '...';
                        }
                    }

                    if ($short !== null) {
                        $line[] = "{$b}-{$short}{$b}";
                        $value[] = $option->ValueRequired
                            ? " {}{$ellipsis}"
                            : "{$esc}[{}{$ellipsis}]";
                    }

                    if ($long !== null) {
                        $line[] = "{$b}--{$long}{$b}";
                        $value[] = $option->ValueRequired
                            ? " {}{$ellipsis}"
                            : "{$esc}[={}{$ellipsis}]";
                    }
                }
            }

            $line = $prefix . implode(', ', $line) . array_pop($value) . $suffix;

            // Replace value name with allowed values if $synopsis won't break
            // over multiple lines, otherwise add them after the description
            if (($pos = strrpos($line, '{}')) !== false) {
                $synopsis = null;
                if ($allowed) {
                    [$before, $after] = $option->ValueRequired ? ['(', ')'] : ['', ''];
                    $allowedSynopsis = substr_replace($line, $before . implode('|', $allowed) . $after, $pos, 2);
                    if (mb_strlen($formatter->removeTags($indent . $allowedSynopsis)) <= ($width ?? 76)) {
                        $synopsis = $allowedSynopsis;
                        $allowed = null;
                    }
                }
                if ($synopsis === null) {
                    $synopsis = substr_replace($line, $valueName, $pos, 2);
                }
            } else {
                $synopsis = $line;
            }

            $lines = [];
            if ($option->Description !== null &&
                    ($description = trim($option->Description)) !== '') {
                $lines[] = $this->prepareUsage($description, $formatter, $width, $indent);
            }

            $valueName = $option->getValueName();

            if ($allowed) {
                foreach ($allowed as &$value) {
                    $value = sprintf('%s- %s', $indent, $value);
                }
                $lines[] = sprintf(
                    "%sThe %s can be:\n\n%s",
                    $indent,
                    $valueName,
                    implode("\n", $allowed)
                );
            }

            if (!$option->IsFlag &&
                $option->OriginalDefaultValue !== null &&
                $option->OriginalDefaultValue !== [] &&
                (!($option->Visibility & CliOptionVisibility::HIDE_DEFAULT) ||
                    $visibility === CliOptionVisibility::HELP)) {
                foreach ((array) $option->OriginalDefaultValue as $value) {
                    if ((string) $value === '') {
                        continue;
                    }
                    $default[] = $em . $formatter->escapeTags((string) $value) . $em;
                }
                $default = implode($option->Delimiter ?? ' ', $default);
                if ($default !== '') {
                    $lines[] = sprintf(
                        '%sThe default %s is: %s',
                        $indent,
                        $valueName,
                        $default,
                    );
                }
            }

            $options[] = $beforeSynopsis . $synopsis
                . ($lines ? $beforeDescription . ltrim(implode("\n\n", $lines)) : '');
        }

        $name = $this->getNameWithProgram();
        if ($sections = $this->getHelpSections() ?: []) {
            $sections = array_map(
                fn(string $section) => $this->prepareUsage($section, $formatter, $width),
                $sections
            );
        }

        return [
            'NAME' => $name . ' - ' . $this->description(),
            'SYNOPSIS' => $this->getSynopsis($withMarkup, $width, $collapse),
            'DESCRIPTION' => $this->prepareUsage($this->getLongDescription(), $formatter, $width),
            'OPTIONS' => implode("\n\n", $options),
        ] + $sections;
    }

    private function prepareUsage(?string $description, Formatter $formatter, ?int $width, ?string $indent = null): string
    {
        if ((string) $description === '') {
            return '';
        }

        $description = $formatter->formatTags(
            str_replace(
                ['{{program}}', '{{command}}'],
                [$this->App->getProgramName(), $this->getNameWithProgram()],
                $description
            ),
            true,
            ($width ?: 76) - ($indent ? strlen($indent) : 0),
            false
        );

        if ($indent) {
            return $indent . str_replace("\n", "\n{$indent}", $description);
        }

        return $description;
    }

    /**
     * @inheritDoc
     */
    final public function getSynopsis(bool $withMarkup = true, ?int $width = 80, bool $collapse = false): string
    {
        $style = $withMarkup
            ? $this->App->getHelpStyle()
            : new CliHelpStyle();

        $b = $style->Bold;
        $prefix = $style->SynopsisPrefix;
        $newline = $style->SynopsisNewline;

        $name = $b . $this->getNameWithProgram() . $b;
        $full = $this->getOptionsSynopsis($withMarkup, $style, $collapsed);
        $synopsis = Arr::implode(' ', [$name, $full]);

        if ($width !== null) {
            $wrapped = $prefix . str_replace(
                "\n", $newline, $this
                    ->getLoopbackFormatter()
                    ->formatTags($synopsis, false, [$width, $width - 4], !$withMarkup)
            );

            if (!$collapse || strpos($wrapped, "\n") === false) {
                return $wrapped;
            }

            $synopsis = Arr::implode(' ', [$name, $collapsed]);
        }

        return $prefix . $this
            ->getLoopbackFormatter()
            ->formatTags($synopsis, false, null, !$withMarkup);
    }

    private function getOptionsSynopsis(bool $withMarkup, CliHelpStyle $style, ?string &$collapsed = null): string
    {
        $b = $style->Bold;

        // Produce this:
        //
        //     [-ny] [--exclude PATTERN] [--verbose] --from SOURCE DEST
        //
        // By generating this:
        //
        //     $shortFlag = ['n', 'y'];
        //     $optional = ['[--exclude PATTERN]', '[--verbose]'];
        //     $required = ['--from SOURCE'];
        //     $positional = ['DEST'];
        //
        $shortFlag = [];
        $optional = [];
        $required = [];
        $positional = [];

        $count = 0;
        foreach ($this->getOptions() as $option) {
            if (!($option->Visibility & CliOptionVisibility::SYNOPSIS)) {
                continue;
            }

            if ($option->IsFlag || !($option->IsPositional || $option->WasRequired)) {
                $count++;
                if ($option->MultipleAllowed) {
                    $count++;
                }
            }

            if ($option->IsFlag) {
                if ($option->Short !== null) {
                    $shortFlag[] = $option->Short;
                    continue;
                }
                $optional[] = "\[{$b}--{$option->Long}{$b}]";
                continue;
            }

            $valueName = $option->formatValueName();
            if (!$withMarkup) {
                $valueName = Formatter::escapeTags($valueName);
            }

            if ($option->IsPositional) {
                if ($option->MultipleAllowed) {
                    $valueName .= '...';
                }
                $positional[] = $option->WasRequired ? $valueName : "\[{$valueName}]";
                continue;
            }

            $prefix = '';
            $suffix = '';
            $ellipsis = '';
            if ($option->MultipleAllowed) {
                if ($option->Delimiter) {
                    $valueName .= "{$option->Delimiter}...";
                } elseif ($option->ValueRequired) {
                    if ($option->WasRequired) {
                        $prefix = '\(';
                        $suffix = ')...';
                    } else {
                        $ellipsis = '...';
                    }
                } else {
                    $ellipsis = '...';
                }
            }

            $valueName = $option->Short !== null
                ? "{$prefix}{$b}-{$option->Short}{$b}"
                    . ($option->ValueRequired
                        ? "\ {$valueName}{$suffix}"
                        : "\[{$valueName}]{$suffix}")
                : "{$prefix}{$b}--{$option->Long}{$b}"
                    . ($option->ValueRequired
                        ? "\ {$valueName}{$suffix}"
                        : "\[={$valueName}]{$suffix}");

            if ($option->WasRequired) {
                $required[] = $valueName . $ellipsis;
            } else {
                $optional[] = "\[$valueName]" . $ellipsis;
            }
        }

        $collapsed = implode(' ', array_filter([
            $count > 1 ? '\[<option>]...' : '',
            $count === 1 ? '\[<option>]' : '',
            $required ? implode(' ', $required) : '',
            $positional ? "\[{$b}--{$b}] " . implode(' ', $positional) : '',
        ]));

        return implode(' ', array_filter([
            $shortFlag ? "\[{$b}-" . implode('', $shortFlag) . "{$b}]" : '',
            $optional ? implode(' ', $optional) : '',
            $required ? implode(' ', $required) : '',
            $positional ? "\[{$b}--{$b}] " . implode(' ', $positional) : '',
        ]));
    }

    /**
     * Get the command name, including the name used to run the script, as a
     * string of space-delimited subcommands
     */
    final protected function getNameWithProgram(): string
    {
        $name = $this->Name ?? [];
        array_unshift($name, $this->App->getProgramName());

        return implode(' ', $name);
    }

    /**
     * @return array{'$schema':string,title?:string,type:string,required?:string[],properties?:array<string,mixed>,...}
     */
    final public function getJsonSchema(?string $title = null): array
    {
        $schema = [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
        ];
        $schema['title'] = $title;
        $schema['type'] = 'object';
        $schema['required'] = [];
        $schema['properties'] = [];

        foreach ($this->getOptions() as $option) {
            if (!($option->Visibility & CliOptionVisibility::SCHEMA)) {
                continue;
            }

            $name = Str::toCamelCase((string) $option->Name);
            if ($name === '' || isset($schema['properties'][$name])) {
                throw new LogicException(sprintf('Schema option names must be unique and non-empty after camelCase conversion: %s', $option->Name));
            }

            $schema['properties'][$name] = $option->getJsonSchema();

            if ($option->Required) {
                $schema['required'][] = $name;
            }
        }

        // Preserve essential properties in their original order
        $schema = array_merge($schema, $this->filterJsonSchema($schema));

        foreach (['title', 'required', 'properties'] as $property) {
            $value = $schema[$property];
            if ($value === null || $value === []) {
                unset($schema[$property]);
            }
        }

        return $schema;
    }

    /**
     * Optionally normalise an array of option values, assign them to the
     * command, and return them to the caller
     *
     * @param array<string,array<string|int|true>|string|int|bool|null> $values
     * An array that maps options to values.
     * @param bool $normalise `false` if `$value` has already been normalised.
     * @param bool $expand If `true`, replace `null` (or `true`, if the option
     * is not a flag and doesn't have type {@see CliOptionValueType::BOOLEAN})
     * with the default value of the option if it has an optional value. Ignored
     * if `$normalise` is `false`.
     * @param bool $asArguments If `true`, assign the values to the command as
     * if they had been given on the command line.
     * @return array<string,mixed>
     * @see CliCommand::normaliseOptionValues()
     */
    final protected function applyOptionValues(
        array $values,
        bool $normalise = true,
        bool $expand = false,
        bool $asArguments = false
    ): array {
        $this->assertHasRun();

        $_values = [];
        foreach ($values as $name => $value) {
            if ($name === '@internal') {
                $_values[$name] = $value;
                continue;
            }
            $option = $this->OptionsByName[$name] ?? null;
            if (!$option) {
                throw new LogicException(sprintf('Option not found: %s', $name));
            }
            $value = $option->applyValue($_value = $value, $normalise, $expand);
            $this->OptionValues[$option->Key] = $_values[$name] = $value;
            if ($asArguments) {
                $this->ArgumentValues[$option->Key] = $_value;
            }
        }

        return $_values;
    }

    /**
     * Normalise an array that maps options to user-supplied values
     *
     * @param array<string,array<string|int>|string|int|bool|null> $values
     * @param bool $expand If `true`, replace `null` (or `true`, if the option
     * is not a flag and doesn't have type {@see CliOptionValueType::BOOLEAN})
     * with the default value of the option if it has an optional value.
     * @param (callable(string): string)|null $nameCallback
     * @param array<string,array<string|int>|string|int|bool|null>|null $invalid
     * @return array<string,mixed>
     */
    final protected function normaliseOptionValues(
        array $values,
        bool $expand = false,
        ?callable $nameCallback = null,
        ?array &$invalid = null
    ): array {
        $this->loadOptions();

        $_values = [];
        foreach ($values as $name => $value) {
            if ($name === '@internal') {
                $_values[$name] = $value;
                continue;
            }
            $_name = $nameCallback ? $nameCallback($name) : $name;
            $option = $this->OptionsByName[$_name] ?? null;
            if (!$option) {
                $invalid[$name] = $value;
                continue;
            }
            $_values[$_name] = $option->normaliseValue($value, $expand);
        }

        return $_values;
    }

    /**
     * Get an array that maps options to values
     *
     * The long form of the option (e.g. 'verbose') is used if available. The
     * short form (e.g. 'v') is only used if the option has no long form.
     *
     * @param bool $export If `true`, only options with user-supplied values are
     * returned, otherwise the value of every option is returned.
     * @param (callable(string): string)|null $nameCallback
     * @return array<string,array<string|int>|string|int|bool|null>
     */
    final protected function getOptionValues(
        bool $export = false,
        ?callable $nameCallback = null
    ): array {
        $this->assertHasRun();

        $values = [];
        foreach ($this->Options as $option) {
            $value = $this->OptionValues[$option->Key] ?? null;
            $wasArg = array_key_exists($option->Key, $this->ArgumentValues);
            if ($export &&
                (!$wasArg ||
                    // Skip this option if `$value` is empty because
                    // user-supplied values were discarded
                    ($value === [] && $option->OriginalDefaultValue === $value)) &&
                (($option->IsFlag && !$value) ||
                    ($option->ValueRequired && $option->OriginalDefaultValue === $value) ||
                    ($option->ValueOptional && $value === null))) {
                continue;
            }
            $name = $option->Name;
            if ($nameCallback) {
                $name = $nameCallback($name);
            }
            $values[$name] = $export && $wasArg && $option->ValueOptional
                ? Convert::coalesce(
                    $this->ArgumentValues[$option->Key],
                    $option->ValueType !== CliOptionValueType::BOOLEAN ? true : null
                )
                : $value;
        }

        return $values;
    }

    /**
     * Get an array that maps options to default values
     *
     * The long form of the option (e.g. 'verbose') is used if available. The
     * short form (e.g. 'v') is only used if the option has no long form.
     *
     * @param (callable(string): string)|null $nameCallback
     * @return array<string,array<string|int>|string|int|bool|null>
     */
    final protected function getDefaultOptionValues(?callable $nameCallback = null): array
    {
        $this->loadOptions();

        $values = [];
        foreach ($this->Options as $option) {
            $value = $option->ValueOptional ? null : $option->OriginalDefaultValue;
            $name = $option->Name;
            if ($nameCallback) {
                $name = $nameCallback($name);
            }
            $values[$name] = $value;
        }

        return $values;
    }

    /**
     * Get the value of a command line option
     *
     * For values that can be given multiple times, an array of values will be
     * returned. For flags that can be given multiple times, the number of uses
     * will be returned.
     *
     * @param string $name The value of the option's {@see CliOption::$Name},
     * {@see CliOption::$Long} or {@see CliOption::$Short} property.
     * @return array<string|int>|string|int|bool|null
     */
    final protected function getOptionValue(string $name)
    {
        $this->assertHasRun();

        if (!($option = $this->getOption($name))) {
            throw new LogicException("No option with name '$name'");
        }

        return $this->OptionValues[$option->Key] ?? null;
    }

    final protected function getOption(string $name): ?CliOption
    {
        return $this->loadOptions()->OptionsByName[$name] ?? null;
    }

    final protected function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->loadOptions()->OptionsByName);
    }

    private function loadOptionValues(): void
    {
        $this->loadOptions();

        try {
            $merged = $this->mergeArguments(
                $this->Arguments,
                $this->ArgumentValues,
                $this->NextArgumentIndex,
                $this->HasHelpArgument,
                $this->HasVersionArgument
            );

            foreach ($this->Options as $option) {
                if ($option->Required &&
                        (!array_key_exists($option->Key, $merged) || $merged[$option->Key] === [])) {
                    if (!(count($this->Arguments) === 1 && ($this->HasHelpArgument || $this->HasVersionArgument))) {
                        $this->optionError(sprintf(
                            '%s required%s',
                            $option->DisplayName,
                            $option->formatAllowedValues()
                        ));
                    }
                    continue;
                }

                $value = $merged[$option->Key] ?? ($option->ValueRequired ? $option->DefaultValue : null);
                try {
                    $value = $option->applyValue($value);
                    if ($value !== null || array_key_exists($option->Key, $merged)) {
                        $this->OptionValues[$option->Key] = $value;
                    }
                } catch (CliInvalidArgumentsException $ex) {
                    foreach ($ex->getErrors() as $error) {
                        $this->optionError($error);
                    }
                } catch (CliUnknownValueException $ex) {
                    $this->optionError($ex->getMessage());
                }
            }

            if ($this->OptionErrors) {
                throw new CliInvalidArgumentsException(
                    ...$this->OptionErrors,
                    ...$this->DeferredOptionErrors
                );
            }
        } catch (Throwable $ex) {
            $this->OptionValues = null;

            throw $ex;
        }
    }

    /**
     * @param string[] $args
     * @param array<string,array<string|int|true>|string|int|bool|null> $argValues
     * @return array<string,array<string|int|true>|string|int|bool|null>
     */
    private function mergeArguments(
        array $args,
        ?array &$argValues,
        ?int &$nextArgumentIndex,
        ?bool &$hasHelpArgument,
        ?bool &$hasVersionArgument
    ): array {
        $saveArgValue =
            function (string $key, $value) use (&$argValues, &$saved, &$option) {
                if ($saved) {
                    return;
                }
                $saved = true;
                /** @var CliOption $option */
                if (!array_key_exists($key, $argValues) ||
                        ($option->IsFlag && !$option->MultipleAllowed)) {
                    $argValues[$key] = $value;
                } else {
                    $argValues[$key] = array_merge((array) $argValues[$key], Arr::wrap($value));
                }
            };
        $merged = [];
        $positional = [];

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            $short = false;
            $saved = false;
            if (preg_match('/^-([a-z0-9_])(.*)/i', $arg, $matches)) {
                $name = $matches[1];
                $value = $matches[2] === '' ? null : $matches[2];
                $short = true;
            } elseif (preg_match('/^--([a-z0-9_][-a-z0-9_]+)(?:=(.*))?$/i', $arg, $matches, \PREG_UNMATCHED_AS_NULL)) {
                $name = $matches[1];
                $value = $matches[2];
            } elseif ($arg === '--') {
                $i++;
                break;
            } elseif ($arg === '-' || ($arg[0] ?? null) !== '-') {
                $positional[] = $arg;
                continue;
            } else {
                $this->optionError(sprintf("invalid argument '%s'", $arg));
                continue;
            }

            $option = $this->OptionsByName[$name] ?? null;
            if (!$option || $option->IsPositional) {
                $this->optionError(sprintf("unknown option '%s'", $name));
                continue;
            }

            if ($option->Long === 'help') {
                $hasHelpArgument = true;
            } elseif ($option->Long === 'version') {
                $hasVersionArgument = true;
            }

            $key = $option->Key;
            if ($option->IsFlag) {
                // Handle multiple short flags per argument, e.g. `cp -rv`
                if ($short && $value !== null) {
                    $args[$i] = "-$value";
                    $i--;
                }
                $value = true;
                $saveArgValue($key, $value);
            } elseif ($option->ValueOptional) {
                // Don't use the default value if `--option=` was given
                if ($value === null) {
                    $saveArgValue($key, $value);
                    $value = $option->DefaultValue;
                }
            } elseif ($value === null) {
                $i++;
                if (($value = $args[$i] ?? null) === null) {
                    // Allow null to be stored to prevent an additional
                    // "argument required" error
                    $this->optionError(sprintf(
                        '%s requires a value%s',
                        $option->DisplayName,
                        $option->formatAllowedValues()
                    ));
                    $i--;
                }
            }

            if ($option->MultipleAllowed && !$option->IsFlag) {
                // Interpret the first use of "--opt=" as "clear default or
                // previous values" without changing the meaning of "--opt ''"
                if ($option->ValueRequired && $value === '') {
                    if ($args[$i] === '' || ($merged[$key] ?? null) === []) {
                        $value = [''];
                    } else {
                        $merged[$key] = [];
                        $argValues[$key] = [];
                        continue;
                    }
                } else {
                    $value = $option->maybeSplitValue($value);
                }
                $saveArgValue($key, $value);
            }

            if (array_key_exists($key, $merged) &&
                    !($option->IsFlag && !$option->MultipleAllowed)) {
                $merged[$key] = array_merge((array) $merged[$key], Arr::wrap($value));
            } else {
                $merged[$key] = $value;
            }
        }

        // Splice $positional into $args to ensure $nextArgumentIndex is correct
        if ($positional) {
            $i -= count($positional);
            array_splice($args, $i, count($positional), $positional);
        }
        $pending = count($this->PositionalOptions);
        foreach ($this->PositionalOptions as $option) {
            if (!($i < count($args))) {
                break;
            }
            $pending--;
            $key = $option->Key;
            $saved = false;
            if ($option->Required || !$option->MultipleAllowed) {
                $arg = $args[$i++];
                $merged[$key] = $option->MultipleAllowed ? [$arg] : $arg;
                $saveArgValue($key, $arg);
                if (!$option->MultipleAllowed) {
                    continue;
                }
            }
            // Only one positional option can accept multiple values, so collect
            // arguments until all that remains is one per pending option
            while (count($args) - $i - $pending > 0) {
                $saved = false;
                $arg = $args[$i++];
                $merged[$key][] = $arg;
                $saveArgValue($key, $arg);
            }
        }

        $nextArgumentIndex = $i;

        return $merged;
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
     * @return CliOption[]
     */
    final protected function getOptions(): array
    {
        return array_values($this->loadOptions()->Options);
    }

    /**
     * @return $this
     */
    private function loadOptions()
    {
        if ($this->Options !== null) {
            return $this;
        }

        try {
            foreach ($this->getOptionList() as $option) {
                $this->addOption($option);
            }

            return $this
                ->maybeAddHiddenOption('help')
                ->maybeAddHiddenOption('version');
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
    private function maybeAddHiddenOption(string $long)
    {
        if (!array_key_exists($long, $this->OptionsByName)) {
            $this->addOption(CliOption::build()->long($long)->hide());
        }
        return $this;
    }

    /**
     * @param CliOption|CliOptionBuilder $option
     */
    private function addOption($option): void
    {
        try {
            $option = $option->load();
        } catch (CliUnknownValueException $ex) {
            // If an exception is thrown over a value found in the environment,
            // defer it in case we're only loading options for a help message
            $this->DeferredOptionErrors[] = $ex->getMessage();
        }

        $names = $option->getNames();

        if (array_intersect_key(array_flip($names), $this->OptionsByName)) {
            throw new LogicException('Option names must be unique: ' . implode(', ', $names));
        }

        if ($option->IsPositional) {
            if ($option->WasRequired &&
                    array_filter(
                        $this->PositionalOptions,
                        fn(CliOption $opt) =>
                            !$opt->WasRequired && !$opt->MultipleAllowed
                    )) {
                throw new LogicException('Required positional options must be added before optional ones');
            }

            if (!$option->WasRequired &&
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
    }

    /**
     * @return $this
     */
    private function assertHasRun()
    {
        if ($this->OptionValues === null) {
            throw new LogicException('Command must be invoked first');
        }

        return $this;
    }

    /**
     * True if the command is currently running
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
     */
    final protected function getRuns(): int
    {
        return $this->Runs;
    }

    private function reset(): void
    {
        $this->Arguments = [];
        $this->ArgumentValues = [];
        $this->OptionValues = null;
        $this->OptionErrors = [];
        $this->NextArgumentIndex = null;
        $this->HasHelpArgument = false;
        $this->HasVersionArgument = false;
        $this->ExitStatus = 0;
    }

    private function getLoopbackFormatter(): Formatter
    {
        return $this->LoopbackFormatter
            ??= new Formatter(ConsoleLoopbackFormat::getTagFormats());
    }
}
