<?php declare(strict_types=1);

namespace Salient\Cli;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\Exception\CliUnknownValueException;
use Salient\Contract\Cli\CliApplicationInterface;
use Salient\Contract\Cli\CliCommandInterface;
use Salient\Contract\Cli\CliHelpSectionName;
use Salient\Contract\Cli\CliHelpStyleInterface;
use Salient\Contract\Cli\CliHelpTarget;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Contract\Cli\CliOptionVisibility;
use Salient\Contract\Core\HasJsonSchema;
use Salient\Core\Facade\Console;
use Salient\Utility\Exception\InvalidRuntimeConfigurationException;
use Salient\Utility\Arr;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Salient\Utility\Sys;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Base class for runnable CLI commands
 */
abstract class CliCommand implements CliCommandInterface
{
    protected CliApplicationInterface $App;
    /** @var string[] */
    private array $Name = [];
    /** @var array<string,CliOption>|null */
    private ?array $Options = null;
    /** @var array<string,CliOption> */
    private array $OptionsByName = [];
    /** @var array<string,CliOption> */
    private array $PositionalOptions = [];
    /** @var array<string,CliOption> */
    private array $SchemaOptions = [];
    /** @var string[] */
    private array $DeferredOptionErrors = [];
    /** @var string[] */
    private array $Arguments = [];
    /** @var array<string,array<string|int|bool|float>|string|int|bool|float|null> */
    private array $ArgumentValues = [];
    /** @var array<string,mixed>|null */
    private ?array $OptionValues = null;
    /** @var string[] */
    private array $OptionErrors = [];
    private ?int $NextArgumentIndex = null;
    private bool $HasHelpArgument = false;
    private bool $HasVersionArgument = false;
    private bool $IsRunning = false;
    private int $ExitStatus = 0;
    private int $Runs = 0;

    /**
     * Run the command
     *
     * The command's return value will be:
     *
     * 1. the return value of this method (if an `int` is returned)
     * 2. the last value passed to {@see CliCommand::setExitStatus()}, or
     * 3. `0`, indicating success
     *
     * @param string ...$args Non-option arguments passed to the command.
     * @return int|void
     */
    abstract protected function run(string ...$args);

    /**
     * Override to allow the command to run as the root user
     *
     * This method is called immediately before {@see CliCommand::run()}, after
     * command-line arguments are parsed.
     */
    protected function canRunAsRoot(): bool
    {
        return false;
    }

    /**
     * Override to return a list of options for the command
     *
     * @return iterable<CliOption|CliOptionBuilder>
     */
    protected function getOptionList(): iterable
    {
        return [];
    }

    /**
     * Override to return a detailed description of the command
     */
    protected function getLongDescription(): ?string
    {
        return null;
    }

    /**
     * Override to return content for the command's help message
     *
     * `"NAME"`, `"SYNOPSIS"`, `"OPTIONS"` and `"DESCRIPTION"` sections are
     * generated automatically and must not be returned by this method.
     *
     * @return array<CliHelpSectionName::*|string,string> An array that maps
     * section names to content.
     */
    protected function getHelpSections(): array
    {
        return [];
    }

    /**
     * Override to modify the command's JSON Schema before it is returned
     *
     * @param array{'$schema':string,type:string,required:string[],properties:array<string,mixed>} $schema
     * @return array{'$schema':string,type:string,required:string[],properties:array<string,mixed>,...}
     */
    protected function filterJsonSchema(array $schema): array
    {
        return $schema;
    }

    /**
     * Override to modify schema option values before they are returned
     *
     * @param mixed[] $values
     * @return mixed[]
     */
    protected function filterGetSchemaValues(array $values): array
    {
        return $values;
    }

    /**
     * Override to modify schema option values before they are normalised
     *
     * @param array<array<string|int|bool|float>|string|int|bool|float|null> $values
     * @return array<array<string|int|bool|float>|string|int|bool|float|null>
     */
    protected function filterNormaliseSchemaValues(array $values): array
    {
        return $values;
    }

    /**
     * Override to modify schema option values before they are applied to the
     * command
     *
     * {@see filterNormaliseSchemaValues()} is always applied to `$values`
     * before {@see filterApplySchemaValues()}.
     *
     * @param array<array<string|int|bool|float>|string|int|bool|float|null> $values
     * @param bool $normalised `true` if `$values` have been normalised,
     * otherwise `false`.
     * @return array<array<string|int|bool|float>|string|int|bool|float|null>
     */
    protected function filterApplySchemaValues(array $values, bool $normalised): array
    {
        return $values;
    }

    public function __construct(CliApplicationInterface $app)
    {
        $this->App = $app;
    }

    /**
     * @inheritDoc
     */
    final public function __invoke(string ...$args): int
    {
        $this->reset();

        $this->Arguments = $args;
        $this->Runs++;

        $this->loadOptionValues();

        if ($this->HasHelpArgument) {
            $style = new CliHelpStyle(CliHelpTarget::NORMAL);
            Console::printStdout($style->buildHelp($this->getHelp($style)));
            return 0;
        }

        if ($this->HasVersionArgument) {
            Console::printStdout($this->App->getVersionString());
            return 0;
        }

        if ($this->DeferredOptionErrors) {
            throw new CliInvalidArgumentsException(
                ...$this->DeferredOptionErrors,
            );
        }

        if (!$this->canRunAsRoot() && Sys::isRunningAsRoot()) {
            // @codeCoverageIgnoreStart
            throw new InvalidRuntimeConfigurationException(sprintf(
                'Command cannot run as root: %s',
                $this->getNameWithProgram(),
            ));
            // @codeCoverageIgnoreEnd
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
     * @internal
     */
    final public function __clone()
    {
        $this->Options = null;
        $this->OptionsByName = [];
        $this->PositionalOptions = [];
        $this->SchemaOptions = [];
        $this->DeferredOptionErrors = [];
    }

    /**
     * @inheritDoc
     */
    final public function getContainer(): CliApplicationInterface
    {
        return $this->App;
    }

    /**
     * @inheritDoc
     */
    final public function getName(): string
    {
        return implode(' ', $this->Name);
    }

    /**
     * @inheritDoc
     */
    final public function getNameParts(): array
    {
        return $this->Name;
    }

    /**
     * @inheritDoc
     */
    final public function setName(array $name): void
    {
        $this->Name = $name;
    }

    /**
     * @inheritDoc
     */
    final public function getHelp(?CliHelpStyleInterface $style = null): array
    {
        $style ??= new CliHelpStyle();

        $b = $style->getBold();
        $em = $style->getItalic();
        $esc = $style->getEscape();
        $indent = $style->getOptionIndent();
        $optionPrefix = $style->getOptionPrefix();
        $descriptionPrefix = $style->getOptionDescriptionPrefix();
        /** @var int&CliOptionVisibility::* */
        $visibility = $style->getVisibility();
        $width = $style->getWidth();

        $formatter = $style->getFormatter();

        $options = [];
        foreach ($this->_getOptions() as $option) {
            if (!($option->Visibility & $visibility)) {
                continue;
            }

            $short = $option->Short;
            $long = $option->Long;
            $line = [];
            $value = [];
            $allowed = null;
            $booleanValue = false;
            $valueName = null;
            $default = [];
            $prefix = '';
            $suffix = '';

            if ($option->IsFlag) {
                if ($short !== null) {
                    $line[] = $b . '-' . $short . $b;
                }
                if ($long !== null) {
                    $line[] = $b . '--' . $long . $b;
                }
            } else {
                if (
                    $option->AllowedValues
                    || $option->ValueType === CliOptionValueType::BOOLEAN
                ) {
                    foreach ($option->AllowedValues ?: [true, false] as $optionValue) {
                        $optionValue = $option->normaliseValueForHelp($optionValue);
                        $allowed[] = $em . Console::escape($optionValue) . $em;
                    }
                    if (!$option->AllowedValues) {
                        $booleanValue = true;
                    }
                }

                // `{}` is a placeholder for value name / allowed value list
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
                            $ellipsis = $option->Delimiter . '...';
                        } elseif ($option->ValueRequired) {
                            $prefix = $esc . '(';
                            $suffix = ')...';
                        } else {
                            $suffix = '...';
                        }
                    }

                    if ($short !== null) {
                        $line[] = $b . '-' . $short . $b;
                        $value[] = $option->ValueRequired
                            ? ' {}' . $ellipsis
                            : $esc . '[{}' . $ellipsis . ']';
                    }

                    if ($long !== null) {
                        $line[] = $b . '--' . $long . $b;
                        $value[] = $option->ValueRequired
                            ? ' {}' . $ellipsis
                            : $esc . '[={}' . $ellipsis . ']';
                    }
                }
            }

            $line = $prefix . implode(', ', $line) . array_pop($value) . $suffix;

            // Replace value name with allowed values if $synopsis won't break
            // over multiple lines, otherwise add them after the description
            $pos = strrpos($line, '{}');
            if ($pos !== false) {
                $_line = null;
                if (!$option->IsPositional && $allowed) {
                    if ($option->ValueRequired) {
                        $prefix = '(';
                        $suffix = ')';
                    } else {
                        $prefix = '';
                        $suffix = '';
                    }
                    $_line = substr_replace(
                        $line,
                        $prefix . implode('|', $allowed) . $suffix,
                        $pos,
                        2
                    );
                    if (
                        $booleanValue
                        || mb_strlen($formatter->wrapsAfterFormatting()
                            ? $formatter->format($indent . $_line)
                            : Console::removeTags($indent . $_line)) <= ($width ?: 76)
                    ) {
                        $allowed = null;
                    } else {
                        $_line = null;
                    }
                }
                if ($_line === null) {
                    $_line = substr_replace($line, $option->getValueName(true), $pos, 2);
                }
                $line = $_line;
            }

            $lines = [];
            $description = trim((string) $option->Description);
            if ($description !== '') {
                $lines[] = $this->prepareHelp($style, $description, $indent);
            }

            if ($allowed) {
                foreach ($allowed as &$value) {
                    $value = sprintf('%s- %s', $indent, $value);
                }
                $valueName = $option->getValueNameWords();
                $lines[] = sprintf(
                    "%sThe %s can be:\n\n%s",
                    $indent,
                    $valueName,
                    implode("\n", $allowed),
                );
            }

            if (
                !$option->IsFlag
                && $option->OriginalDefaultValue !== null
                && $option->OriginalDefaultValue !== []
                && (
                    !($option->Visibility & CliOptionVisibility::HIDE_DEFAULT)
                    || $visibility === CliOptionVisibility::HELP
                )
            ) {
                foreach ((array) $option->OriginalDefaultValue as $value) {
                    if ((string) $value === '') {
                        continue;
                    }
                    $value = $option->normaliseValueForHelp($value);
                    $default[] = $em . Console::escape($value) . $em;
                }
                $default = implode(Str::coalesce($option->Delimiter, ' '), $default);
                if ($default !== '') {
                    $lines[] = sprintf(
                        '%sThe default %s is: %s',
                        $indent,
                        $valueName ?? $option->getValueNameWords(),
                        $default,
                    );
                }
            }

            $options[] = $optionPrefix . $line
                . ($lines ? $descriptionPrefix . ltrim(implode("\n\n", $lines)) : '');
        }

        $name = Console::escape($this->getNameWithProgram());
        $summary = Console::escape($this->getDescription());
        $synopsis = $this->getSynopsis($style);

        $description = $this->getLongDescription() ?? '';
        if ($description !== '') {
            $description = $this->prepareHelp($style, $description);
        }

        $help = [
            CliHelpSectionName::NAME => $name . ' - ' . $summary,
            CliHelpSectionName::SYNOPSIS => $synopsis,
            CliHelpSectionName::DESCRIPTION => $description,
            CliHelpSectionName::OPTIONS => implode("\n\n", $options),
        ];

        $sections = $this->getHelpSections();
        $invalid = array_intersect_key($sections, $help);
        if ($invalid) {
            throw new LogicException(sprintf(
                '%s must not be returned by %s::getHelpSections()',
                implode(', ', array_keys($invalid)),
                static::class,
            ));
        }
        foreach ($sections as $name => $section) {
            if ($section !== '') {
                $help[$name] = $this->prepareHelp($style, $section);
            }
        }

        return $help;
    }

    private function prepareHelp(CliHelpStyleInterface $style, string $text, string $indent = ''): string
    {
        $text = str_replace(
            ['{{app}}', '{{program}}', '{{command}}', '{{subcommand}}'],
            [
                $this->App->getName(),
                $this->App->getProgramName(),
                $this->getNameWithProgram(),
                Str::coalesce(
                    Arr::last($this->getNameParts()),
                    $this->App->getProgramName(),
                ),
            ],
            $text,
        );

        return $style->prepareHelp($text, $indent);
    }

    /**
     * @inheritDoc
     */
    final public function getSynopsis(?CliHelpStyleInterface $style = null): string
    {
        $style ??= new CliHelpStyle();

        $b = $style->getBold();
        $prefix = $style->getSynopsisPrefix();
        $newline = $style->getSynopsisNewline();
        $softNewline = $style->getSynopsisSoftNewline();
        $width = $style->getWidth();

        // Synopsis newlines are hard line breaks, so wrap without markup
        $formatter = $style->getFormatter()->withWrapAfterFormatting(false);

        $name = $b . $this->getNameWithProgram() . $b;
        $full = $this->getOptionsSynopsis($style, $collapsed);
        $synopsis = Arr::implode(' ', [$name, $full], '');

        if ($width !== null) {
            $wrapped = $formatter->format(
                $synopsis,
                false,
                [$width, $width - 4],
                true,
            );
            $wrapped = $prefix . str_replace("\n", $newline, $wrapped, $count);

            if (!$style->getCollapseSynopsis() || !$count) {
                if ($softNewline !== '') {
                    $wrapped = $style->getFormatter()->format(
                        $wrapped,
                        false,
                        $width,
                        true,
                        $softNewline,
                    );
                }
                return $wrapped;
            }

            $synopsis = Arr::implode(' ', [$name, $collapsed], '');
        }

        $synopsis = $formatter->format(
            $synopsis,
            false,
            $width !== null ? [$width, $width - 4] : null,
            true,
        );
        $synopsis = $prefix . str_replace("\n", $newline, $synopsis);

        if ($width !== null && $softNewline !== '') {
            $synopsis = $style->getFormatter()->format(
                $synopsis,
                false,
                $width,
                true,
                $softNewline,
            );
        }

        return $synopsis;
    }

    /**
     * @param-out string $collapsed
     */
    private function getOptionsSynopsis(CliHelpStyleInterface $style, ?string &$collapsed = null): string
    {
        $b = $style->getBold();
        $esc = $style->getEscape();
        /** @var int&CliOptionVisibility::* */
        $visibility = $style->getVisibility();

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

        $optionalCount = 0;
        foreach ($this->_getOptions() as $option) {
            if (!($option->Visibility & $visibility)) {
                continue;
            }

            if ($option->IsFlag || !($option->IsPositional || $option->WasRequired)) {
                $optionalCount++;
                if ($option->MultipleAllowed) {
                    $optionalCount++;
                }
            }

            if (!($option->Visibility & CliOptionVisibility::SYNOPSIS)) {
                continue;
            }

            if ($option->IsFlag) {
                if ($option->Short !== null) {
                    $shortFlag[] = $option->Short;
                    continue;
                }
                $optional[] = $esc . '[' . $b . '--' . $option->Long . $b . ']';
                continue;
            }

            $valueName = $option->getValueName(true);
            $valueName = $style->maybeEscapeTags($valueName);

            if ($option->IsPositional) {
                if ($option->MultipleAllowed) {
                    $valueName .= '...';
                }
                $positional[] = $option->WasRequired
                    ? $valueName
                    : $esc . '[' . $valueName . ']';
                continue;
            }

            $prefix = '';
            $suffix = '';
            $ellipsis = '';
            if ($option->MultipleAllowed) {
                if ($option->Delimiter) {
                    $valueName .= $option->Delimiter . '...';
                } elseif ($option->ValueRequired) {
                    if ($option->WasRequired) {
                        $prefix = $esc . '(';
                        $suffix = ')...';
                    } else {
                        $ellipsis = '...';
                    }
                } else {
                    $ellipsis = '...';
                }
            }

            $valueName = $option->Short !== null
                ? $prefix . $b . '-' . $option->Short . $b
                    . ($option->ValueRequired
                        ? $esc . ' ' . $valueName . $suffix
                        : $esc . '[' . $valueName . ']' . $suffix)
                : $prefix . $b . '--' . $option->Long . $b
                    . ($option->ValueRequired
                        ? $esc . ' ' . $valueName . $suffix
                        : $esc . '[=' . $valueName . ']' . $suffix);

            if ($option->WasRequired) {
                $required[] = $valueName . $ellipsis;
            } else {
                $optional[] = $esc . '[' . $valueName . ']' . $ellipsis;
            }
        }

        $collapsed = Arr::implode(' ', [
            $optionalCount > 1 ? $esc . '[' . $style->maybeEscapeTags('<options>') . ']' : '',
            $optionalCount === 1 ? $esc . '[' . $style->maybeEscapeTags('<option>') . ']' : '',
            $required ? implode(' ', $required) : '',
            $positional ? $esc . '[' . $b . '--' . $b . '] ' . implode(' ', $positional) : '',
        ], '');

        return Arr::implode(' ', [
            $shortFlag ? $esc . '[' . $b . '-' . implode('', $shortFlag) . $b . ']' : '',
            $optional ? implode(' ', $optional) : '',
            $required ? implode(' ', $required) : '',
            $positional ? $esc . '[' . $b . '--' . $b . '] ' . implode(' ', $positional) : '',
        ], '');
    }

    /**
     * Get the command name, including the name used to run the script, as a
     * string of space-delimited subcommands
     */
    final protected function getNameWithProgram(): string
    {
        return implode(' ', Arr::unshift(
            $this->Name,
            $this->App->getProgramName(),
        ));
    }

    /**
     * Get a JSON Schema for the command's options
     *
     * @return array{'$schema':string,type:string,required?:string[],properties?:array<string,mixed>,...}
     */
    final public function getJsonSchema(): array
    {
        $schema = [
            '$schema' => HasJsonSchema::DRAFT_04_SCHEMA_ID,
        ];
        $schema['type'] = 'object';
        $schema['required'] = [];
        $schema['properties'] = [];

        foreach ($this->_getOptions() as $option) {
            if (!($option->Visibility & CliOptionVisibility::SCHEMA)) {
                continue;
            }

            $name = Str::camel((string) $option->Name);
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

        if ($schema['required'] === []) {
            unset($schema['required']);
        }

        if ($schema['properties'] === []) {
            unset($schema['properties']);
        }

        return $schema;
    }

    /**
     * Normalise an array of option values, apply them to the command, and
     * return them to the caller
     *
     * @param array<array<string|int|bool|float>|string|int|bool|float|null> $values
     * @param bool $normalise `false` if `$values` have already been normalised.
     * @param bool $expand If `true` and an option has an optional value, expand
     * `null` or `true` to the default value of the option. Ignored if
     * `$normalise` is `false`.
     * @param bool $schema If `true`, only apply `$values` to schema options.
     * @param bool $asArguments If `true`, apply `$values` as if they had been
     * given on the command line.
     * @param bool $forgetArguments If `true` and `$asArguments` is also `true`,
     * apply `$values` as if any options previously given on the command line
     * had not been given.
     * @return mixed[]
     */
    final protected function applyOptionValues(
        array $values,
        bool $normalise = true,
        bool $expand = false,
        bool $schema = false,
        bool $asArguments = false,
        bool $forgetArguments = false
    ): array {
        $this->assertHasRun();
        $this->loadOptions();
        if ($asArguments && $forgetArguments) {
            if (!$schema) {
                $this->ArgumentValues = [];
            } else {
                foreach ($this->SchemaOptions as $option) {
                    unset($this->ArgumentValues[$option->Key]);
                }
            }
        }
        if ($schema) {
            if ($normalise) {
                $values = $this->filterNormaliseSchemaValues($values);
            }
            $values = $this->filterApplySchemaValues($values, !$normalise);
        }
        foreach ($values as $name => $value) {
            $option = $this->_getOption($name, $schema);
            if (!$schema) {
                $name = $option->Name;
            }
            $_value = $option->applyValue($value, $normalise, $expand);
            $_values[$name] = $_value;
            $this->OptionValues[$option->Key] = $_value;
            if ($asArguments) {
                // If the option has an optional value and no value was given,
                // store null to ensure it's not expanded on export
                if (
                    $option->ValueOptional
                    && $option->ValueType !== CliOptionValueType::BOOLEAN
                    && $value === true
                ) {
                    $value = null;
                }
                $this->ArgumentValues[$option->Key] = $value;
            }
        }

        return $_values ?? [];
    }

    /**
     * Normalise an array of option values
     *
     * @param array<array<string|int|bool|float>|string|int|bool|float|null> $values
     * @param bool $expand If `true` and an option has an optional value, expand
     * `null` or `true` to the default value of the option.
     * @param bool $schema If `true`, only normalise schema options.
     * @return mixed[]
     */
    final protected function normaliseOptionValues(
        array $values,
        bool $expand = false,
        bool $schema = false
    ): array {
        $this->loadOptions();
        if ($schema) {
            $values = $this->filterNormaliseSchemaValues($values);
        }
        foreach ($values as $name => $value) {
            $option = $this->_getOption($name, $schema);
            if (!$schema) {
                $name = $option->Name;
            }
            $_values[$name] = $option->normaliseValue($value, $expand);
        }

        return $_values ?? [];
    }

    /**
     * Check that an array of option values is valid
     *
     * @param mixed[] $values
     * @phpstan-assert-if-true array<array<string|int|bool|float>|string|int|bool|float|null> $values
     */
    final protected function checkOptionValues(array $values): bool
    {
        foreach ($values as $value) {
            if (
                $value === null
                || is_string($value)
                || is_int($value)
                || is_bool($value)
                || is_float($value)
            ) {
                continue;
            }
            if (!is_array($value)) {
                return false;
            }
            foreach ($value as $v) {
                if (!(is_string($v) || is_int($v) || is_bool($v) || is_float($v))) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Get an array that maps option names to values
     *
     * @param bool $export If `true`, only options given on the command line are
     * returned.
     * @param bool $schema If `true`, an array that maps schema option names to
     * values is returned.
     * @param bool $unexpand If `true` and an option has an optional value not
     * given on the command line, replace its value with `null` or `true`.
     * @return array<array<string|int|bool|float>|string|int|bool|float|null>
     */
    final protected function getOptionValues(
        bool $export = false,
        bool $schema = false,
        bool $unexpand = false
    ): array {
        $this->assertHasRun();
        $this->loadOptions();
        $options = $schema ? $this->SchemaOptions : $this->Options;
        foreach ($options as $key => $option) {
            $given = array_key_exists($option->Key, $this->ArgumentValues);
            if ($export && !$given) {
                continue;
            }
            if ($option->ValueOptional && !$option->Required && !$given) {
                continue;
            }
            $name = $schema ? $key : $option->Name;
            if (
                $unexpand
                && $given
                && $option->ValueOptional
                && $this->ArgumentValues[$option->Key] === null
            ) {
                $value = $option->ValueType !== CliOptionValueType::BOOLEAN
                    ? true
                    : null;
            } else {
                $value = $this->OptionValues[$option->Key] ?? null;
            }
            $values[$name] = $value;
        }

        /** @var array<array<string|int|bool|float>|string|int|bool|float|null> */
        return $schema
            ? $this->filterGetSchemaValues($values ?? [])
            : $values ?? [];
    }

    /**
     * Get an array that maps option names to default values
     *
     * @param bool $schema If `true`, an array that maps schema option names to
     * default values is returned.
     * @return array<array<string|int|bool|float>|string|int|bool|float|null>
     */
    final protected function getDefaultOptionValues(bool $schema = false): array
    {
        $this->loadOptions();
        $options = $schema ? $this->SchemaOptions : $this->Options;
        foreach ($options as $key => $option) {
            if ($option->ValueOptional && !$option->Required) {
                continue;
            }
            $name = $schema ? $key : $option->Name;
            $values[$name] = $option->OriginalDefaultValue;
        }

        /** @var array<array<string|int|bool|float>|string|int|bool|float|null> */
        return $schema
            ? $this->filterGetSchemaValues($values ?? [])
            : $values ?? [];
    }

    /**
     * Get the value of a given option
     *
     * @return mixed
     */
    final protected function getOptionValue(string $name)
    {
        $this->assertHasRun();
        $this->loadOptions();
        $option = $this->_getOption($name, false);
        return $this->OptionValues[$option->Key] ?? null;
    }

    /**
     * True if an option was given on the command line
     */
    final protected function optionHasArgument(string $name): bool
    {
        $this->assertHasRun();
        $this->loadOptions();
        $option = $this->_getOption($name, false);
        return array_key_exists($option->Key, $this->ArgumentValues);
    }

    /**
     * Get the given option
     */
    final protected function getOption(string $name): CliOption
    {
        $this->loadOptions();
        return $this->_getOption($name, false);
    }

    /**
     * True if the command has a given option
     */
    final protected function hasOption(string $name): bool
    {
        $this->loadOptions();
        return isset($this->OptionsByName[$name])
            || isset($this->SchemaOptions[$name]);
    }

    private function _getOption(string $name, bool $schema): CliOption
    {
        if ($schema) {
            $option = $this->SchemaOptions[$name] ?? null;
        } else {
            $option = $this->OptionsByName[$name]
                ?? $this->SchemaOptions[$name]
                ?? null;
        }
        if (!$option) {
            throw new InvalidArgumentException(sprintf(
                '%s not found: %s',
                $schema ? 'Schema option' : 'option',
                $name
            ));
        }
        return $option;
    }

    /**
     * @phpstan-assert !null $this->NextArgumentIndex
     */
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

            $this->OptionValues = [];

            foreach ($this->Options as $option) {
                if (
                    $option->Required
                    && (!array_key_exists($option->Key, $merged) || $merged[$option->Key] === [])
                ) {
                    if (!(count($this->Arguments) === 1 && ($this->HasHelpArgument || $this->HasVersionArgument))) {
                        $this->optionError(sprintf(
                            '%s required%s',
                            $option->DisplayName,
                            $option->formatAllowedValues()
                        ));
                    }
                    continue;
                }

                $value = $merged[$option->Key]
                    ?? ($option->ValueRequired ? $option->DefaultValue : null);
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
     * @param array<string,array<string|int|bool|float>|string|int|bool|float|null> $argValues
     * @param-out int $nextArgumentIndex
     * @return array<string,array<string|int|bool|float>|string|int|bool|float|null>
     */
    private function mergeArguments(
        array $args,
        array &$argValues,
        ?int &$nextArgumentIndex,
        bool &$hasHelpArgument,
        bool &$hasVersionArgument
    ): array {
        $saveArgValue =
            function (string $key, $value) use (&$argValues, &$saved, &$option) {
                if ($saved) {
                    return;
                }
                $saved = true;
                /** @var CliOption $option */
                if (
                    !array_key_exists($key, $argValues)
                    || ($option->IsFlag && !$option->MultipleAllowed)
                ) {
                    $argValues[$key] = $value;
                } else {
                    $argValues[$key] = array_merge((array) $argValues[$key], Arr::wrap($value));
                }
            };

        $merged = [];
        $positional = [];
        $totalArgs = count($args);

        for ($i = 0; $i < $totalArgs; $i++) {
            $arg = $args[$i];
            $short = false;
            $saved = false;
            if (Regex::match('/^-([a-z0-9_])(.*)/i', $arg, $matches)) {
                $name = $matches[1];
                $value = Str::coalesce($matches[2], null);
                $short = true;
            } elseif (Regex::match(
                '/^--([a-z0-9_][-a-z0-9_]+)(?:=(.*))?$/i',
                $arg,
                $matches,
                \PREG_UNMATCHED_AS_NULL,
            )) {
                $name = $matches[1];
                $value = $matches[2];
            } elseif ($arg === '--') {
                $i++;
                break;
            } elseif ($arg === '-' || ($arg !== '' && $arg[0] !== '-')) {
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
                $value = $args[$i] ?? null;
                if ($value === null) {
                    // Allow null to be stored to prevent an additional
                    // "argument required" error
                    $this->optionError(sprintf(
                        '%s requires a value%s',
                        $option->DisplayName,
                        $option->formatAllowedValues(),
                    ));
                    $i--;
                }
            }

            if ($option->MultipleAllowed && !$option->IsFlag) {
                // Interpret "--opt=" as "clear default or previous values" and
                // "--opt ''" as "apply empty string"
                if ($option->ValueRequired && $value === '') {
                    if ($args[$i] === '') {
                        $value = [''];
                    } else {
                        $merged[$key] = [];
                        // @phpstan-ignore parameterByRef.type
                        $argValues[$key] = [];
                        continue;
                    }
                } else {
                    $value = $option->maybeSplitValue($value);
                }
                $saveArgValue($key, $value);
            }

            if (
                array_key_exists($key, $merged)
                && !($option->IsFlag && !$option->MultipleAllowed)
            ) {
                $merged[$key] = array_merge((array) $merged[$key], (array) $value);
            } else {
                $merged[$key] = $value;
            }
            $saveArgValue($key, $value);
        }

        // Splice $positional into $args to ensure $nextArgumentIndex is correct
        if ($positional) {
            $positionalArgs = count($positional);
            $i -= $positionalArgs;
            array_splice($args, $i, $positionalArgs, $positional);
        }
        $pending = count($this->PositionalOptions);
        foreach ($this->PositionalOptions as $option) {
            if ($i >= $totalArgs) {
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
            while ($totalArgs - $i - $pending > 0) {
                $saved = false;
                $arg = $args[$i++];
                $merged[$key] = array_merge((array) ($merged[$key] ?? null), [$arg]);
                $saveArgValue($key, $arg);
            }
        }

        $nextArgumentIndex = $i;

        return $merged;
    }

    private function optionError(string $message): void
    {
        $this->OptionErrors[] = $message;
    }

    /**
     * Get the command's options
     *
     * @return list<CliOption>
     */
    final protected function getOptions(): array
    {
        return array_values($this->_getOptions());
    }

    /**
     * @return array<string,CliOption>
     */
    private function _getOptions(): array
    {
        /** @var array<string,CliOption> */
        $options = $this->loadOptions()->Options;

        return $options;
    }

    /**
     * @return $this
     * @phpstan-assert !null $this->Options
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

            return $this->maybeAddOption('help')->maybeAddOption('version');
        } catch (Throwable $ex) {
            $this->Options = null;
            $this->OptionsByName = [];
            $this->PositionalOptions = [];
            $this->SchemaOptions = [];
            $this->DeferredOptionErrors = [];

            throw $ex;
        }
    }

    /**
     * @return $this
     */
    private function maybeAddOption(string $long)
    {
        if (!isset($this->OptionsByName[$long])) {
            $this->addOption(CliOption::build()->long($long)->hide());
        }
        return $this;
    }

    /**
     * @param CliOption|CliOptionBuilder $option
     */
    private function addOption($option): void
    {
        $option = CliOption::resolve($option);
        try {
            $option = $option->load();
        } catch (CliUnknownValueException $ex) {
            // If an exception is thrown over a value found in the environment,
            // defer it in case we're only loading options for a help message
            $this->DeferredOptionErrors[] = $ex->getMessage();
        }

        $names = $option->getNames();

        if (array_intersect_key(array_flip($names), $this->OptionsByName)) {
            throw new LogicException(sprintf('Option names must be unique: %s', implode(', ', $names)));
        }

        if ($option->Visibility & CliOptionVisibility::SCHEMA) {
            $name = Str::camel((string) $option->Name);
            if ($name === '' || isset($this->SchemaOptions[$name])) {
                throw new LogicException(sprintf(
                    'Schema option names must be unique and non-empty after camelCase conversion: %s',
                    $option->Name
                ));
            }
            $this->SchemaOptions[$name] = $option;
        }

        if ($option->IsPositional) {
            if (
                $option->WasRequired
                && array_filter(
                    $this->PositionalOptions,
                    fn(CliOption $opt) =>
                        !$opt->WasRequired && !$opt->MultipleAllowed
                )
            ) {
                throw new LogicException('Required positional options must be added before optional ones');
            }

            if (
                !$option->WasRequired
                && array_filter(
                    $this->PositionalOptions,
                    fn(CliOption $opt) =>
                        $opt->MultipleAllowed
                )
            ) {
                throw new LogicException("'multipleAllowed' positional options must be added after optional ones");
            }

            if (
                $option->MultipleAllowed
                && array_filter(
                    $this->PositionalOptions,
                    fn(CliOption $opt) =>
                        $opt->MultipleAllowed
                )
            ) {
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
     * @see CliCommand::run()
     *
     * @return $this
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
    final public function getRuns(): int
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
}
