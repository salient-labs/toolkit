<?php declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\Catalog\CliOptionValueUnknownPolicy;
use Lkrms\Cli\Catalog\CliOptionVisibility;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\Exception\CliUnknownValueException;
use Lkrms\Concern\HasBuilder;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleMessageType as MessageType;
use Lkrms\Contract\Buildable;
use Lkrms\Contract\HasJsonSchema;
use Lkrms\Contract\IImmutable;
use Lkrms\Facade\Console;
use Lkrms\Support\Catalog\CharacterSequence as Char;
use Salient\Core\Concern\ReadsProtectedProperties;
use Salient\Core\Contract\Readable;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Env;
use Salient\Core\Utility\Format;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Inflect;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;
use Salient\Core\Utility\Test;
use DateTimeImmutable;
use LogicException;

/**
 * A getopt-style option for a CLI command
 *
 * @property-read string|null $Name The name of the option
 * @property-read string|null $Long The long form of the option, e.g. 'verbose'
 * @property-read string|null $Short The short form of the option, e.g. 'v'
 * @property-read string $Key The option's internal identifier
 * @property-read string|null $ValueName The name of the option's value as it appears in usage information
 * @property-read string $DisplayName The option's name as it appears in error messages
 * @property-read CliOptionType::* $OptionType The option's type
 * @property-read CliOptionValueType::* $ValueType The data type of the option's value
 * @property-read bool $IsFlag True if the option is a flag
 * @property-read bool $IsOneOf True if the option accepts values from a list
 * @property-read bool $IsPositional True if the option is positional
 * @property-read bool $Required True if the option is mandatory
 * @property-read bool $WasRequired True if the option was mandatory before applying values from the environment
 * @property-read bool $ValueRequired True if the option has a mandatory value
 * @property-read bool $ValueOptional True if the option has an optional value
 * @property-read bool $MultipleAllowed True if the option may be given more than once
 * @property-read bool $Unique True if the same value may not be given more than once
 * @property-read string|null $Delimiter The separator between values passed to the option as a single argument
 * @property-read string|null $Description A description of the option
 * @property-read array<string|int|bool>|null $AllowedValues The option's possible values, indexed by lowercase value if not case-sensitive
 * @property-read bool $CaseSensitive True if the option's values are case-sensitive
 * @property-read CliOptionValueUnknownPolicy::*|null $UnknownValuePolicy The action taken if an unknown value is given
 * @property-read bool $AddAll True if 'ALL' should be added to the list of possible values when the option can be given more than once
 * @property-read array<string|int|bool>|string|int|bool|null $DefaultValue Assigned to the option if no value is given on the command line
 * @property-read array<string|int|bool>|string|int|bool|null $OriginalDefaultValue The option's default value before applying values from the environment
 * @property-read bool $Nullable True if the option's value should be null if it is not given on the command line
 * @property-read string|null $EnvVariable The name of a value in the environment that replaces the option's default value
 * @property-read (callable(array<string|int|bool>|string|int|bool): mixed)|null $ValueCallback Applied to the option's value as it is assigned
 * @property-read int-mask-of<CliOptionVisibility::*> $Visibility The option's visibility to users
 * @property-read bool $IsBound True if the option is bound to a variable
 *
 * @implements Buildable<CliOptionBuilder>
 */
final class CliOption implements Buildable, HasJsonSchema, IImmutable, Readable
{
    use HasBuilder;
    use ReadsProtectedProperties;

    private const LONG_REGEX = '/^[a-z0-9_][-a-z0-9_]++$/i';

    private const SHORT_REGEX = '/^[a-z0-9_]$/i';

    private const ONE_OF_INDEX = [
        CliOptionType::ONE_OF => true,
        CliOptionType::ONE_OF_OPTIONAL => true,
        CliOptionType::ONE_OF_POSITIONAL => true,
    ];

    private const POSITIONAL_INDEX = [
        CliOptionType::VALUE_POSITIONAL => true,
        CliOptionType::ONE_OF_POSITIONAL => true,
    ];

    private const VALUE_REQUIRED_INDEX = [
        CliOptionType::VALUE => true,
        CliOptionType::VALUE_POSITIONAL => true,
        CliOptionType::ONE_OF => true,
        CliOptionType::ONE_OF_POSITIONAL => true,
    ];

    private const VALUE_OPTIONAL_INDEX = [
        CliOptionType::VALUE_OPTIONAL => true,
        CliOptionType::ONE_OF_OPTIONAL => true,
    ];

    /**
     * The name of the option
     *
     * May be given instead of {@see CliOption::$Long} if the option is
     * positional.
     */
    protected ?string $Name;

    /**
     * The long form of the option, e.g. "verbose"
     *
     * Must start with a letter, number or underscore, followed by one or more
     * letters, numbers, underscores or hyphens.
     */
    protected ?string $Long;

    /**
     * The short form of the option, e.g. "v"
     *
     * Must contain one letter, number or underscore.
     *
     * Ignored if the option is positional.
     */
    protected ?string $Short;

    /**
     * The option's internal identifier
     */
    protected string $Key;

    /**
     * The name of the option's value as it appears in usage information
     *
     * @see CliOption::formatValueName()
     */
    protected ?string $ValueName;

    /**
     * The option's name as it appears in error messages
     */
    protected string $DisplayName;

    /**
     * The option's type
     *
     * @var CliOptionType::*
     */
    protected int $OptionType;

    /**
     * The data type of the option's value
     *
     * @var CliOptionValueType::*
     */
    protected int $ValueType;

    /**
     * True if the option is a flag
     */
    protected bool $IsFlag;

    /**
     * True if the option accepts values from a list
     */
    protected bool $IsOneOf;

    /**
     * True if the option is positional
     */
    protected bool $IsPositional;

    /**
     * True if the option is mandatory
     */
    protected bool $Required;

    /**
     * True if the option was mandatory before applying values from the
     * environment
     */
    protected bool $WasRequired;

    /**
     * True if the option has a mandatory value
     */
    protected bool $ValueRequired;

    /**
     * True if the option has an optional value
     */
    protected bool $ValueOptional;

    /**
     * True if the option may be given more than once
     */
    protected bool $MultipleAllowed;

    /**
     * True if the same value may not be given more than once
     *
     * Ignored if {@see CliOption::$MultipleAllowed} is `false`.
     */
    protected bool $Unique;

    /**
     * The separator between values passed to the option as a single argument
     *
     * Ignored if {@see CliOption::$MultipleAllowed} is `false`.
     */
    protected ?string $Delimiter;

    /**
     * A description of the option
     */
    protected ?string $Description;

    /**
     * The option's possible values, indexed by lowercase value if not
     * case-sensitive
     *
     * Ignored if {@see CliOption::$IsOneOf} is `false`.
     *
     * @var array<string|int|bool>|null
     */
    protected ?array $AllowedValues;

    /**
     * True if the option's values are case-sensitive
     *
     * If strings in {@see CliOption::$AllowedValues} are unique after
     * conversion to lowercase, {@see CliOption::$CaseSensitive} is `false`.
     *
     * Ignored if {@see CliOption::$IsOneOf} is `false`.
     */
    protected bool $CaseSensitive = true;

    /**
     * The action taken if an unknown value is given
     *
     * Ignored if {@see CliOption::$IsOneOf} is `false`.
     *
     * @var CliOptionValueUnknownPolicy::*|null
     */
    protected ?int $UnknownValuePolicy;

    /**
     * True if "ALL" should be added to the list of possible values when the
     * option can be given more than once
     *
     * Ignored if {@see CliOption::$IsOneOf} or
     * {@see CliOption::$MultipleAllowed} are `false`.
     */
    protected bool $AddAll;

    /**
     * Assigned to the option if no value is given on the command line
     *
     * @var array<string|int|bool>|string|int|bool|null
     */
    protected $DefaultValue;

    /**
     * The option's default value before applying values from the environment
     *
     * @var array<string|int|bool>|string|int|bool|null
     */
    protected $OriginalDefaultValue;

    /**
     * True if the option's value should be null if it is not given on the
     * command line
     */
    protected bool $Nullable;

    /**
     * The name of a value in the environment that replaces the option's default
     * value
     *
     * Ignored if the option is positional.
     */
    protected ?string $EnvVariable;

    /**
     * Applied to the option's value as it is assigned
     *
     * Providing a {@see CliOption::$ValueCallback} disables conversion of the
     * option's value to {@see CliOption::$ValueType}. The callback should
     * return a value of the expected type.
     *
     * @var (callable(array<string|int|bool>|string|int|bool): mixed)|null
     */
    protected $ValueCallback;

    /**
     * The option's visibility to users
     *
     * @var int-mask-of<CliOptionVisibility::*>
     */
    protected int $Visibility;

    /**
     * True if the option is bound to a variable
     */
    protected bool $IsBound;

    /**
     * @var mixed
     * @phpstan-ignore-next-line
     */
    private $BindTo;

    private bool $IsLoaded = false;

    /**
     * Creates a new CliOption object
     *
     * @param CliOptionType::* $optionType
     * @param CliOptionValueType::* $valueType
     * @param array<string|int|bool>|null $allowedValues
     * @param CliOptionValueUnknownPolicy::* $unknownValuePolicy
     * @param array<string|int|bool>|string|int|bool|null $defaultValue
     * @param (callable(array<string|int|bool>|string|int|bool): mixed)|null $valueCallback
     * @param int-mask-of<CliOptionVisibility::*> $visibility
     * @param bool $inSchema True if the option should be included when
     * generating a JSON Schema.
     * @param bool $hide True if the option's visibility should be
     * {@see CliOptionVisibility::NONE}.
     * @param mixed $bindTo Bind the option's value to a variable.
     */
    public function __construct(
        ?string $name,
        ?string $long,
        ?string $short,
        ?string $valueName,
        ?string $description,
        int $optionType = CliOptionType::FLAG,
        int $valueType = CliOptionValueType::STRING,
        ?array $allowedValues = null,
        int $unknownValuePolicy = CliOptionValueUnknownPolicy::REJECT,
        bool $required = false,
        bool $multipleAllowed = false,
        bool $unique = false,
        bool $addAll = false,
        $defaultValue = null,
        bool $nullable = false,
        ?string $envVariable = null,
        ?string $delimiter = ',',
        ?callable $valueCallback = null,
        int $visibility = CliOptionVisibility::ALL,
        bool $inSchema = false,
        bool $hide = false,
        &$bindTo = null
    ) {
        $this->OptionType = $optionType;
        $this->IsFlag = $optionType === CliOptionType::FLAG;
        $this->IsOneOf = self::ONE_OF_INDEX[$optionType] ?? false;
        $this->IsPositional = self::POSITIONAL_INDEX[$optionType] ?? false;
        $this->Required = $required && !$this->IsFlag;
        $this->WasRequired = $this->Required;
        $this->ValueRequired = self::VALUE_REQUIRED_INDEX[$optionType] ?? false;
        $this->ValueOptional = self::VALUE_OPTIONAL_INDEX[$optionType] ?? false;
        $this->MultipleAllowed = $multipleAllowed;
        $this->Unique = $unique && $multipleAllowed;
        $this->EnvVariable = $this->IsPositional ? null : Str::coalesce($envVariable, null);

        $this->Name = Str::coalesce($name, $long, $this->IsPositional ? null : $short, null);
        $this->Long = Str::coalesce($long, null);
        $this->Short = $this->IsPositional ? null : Str::coalesce($short, null);
        $this->Key = $this->IsPositional ? (string) $this->Name : ($short . '|' . $long);
        $this->ValueName = $this->IsFlag ? null : Str::coalesce($valueName, $this->IsPositional ? Str::toKebabCase((string) $this->Name, '=') : null, 'value');
        $this->DisplayName = $this->IsPositional ? $this->formatValueName(false) : ($this->Long !== null ? '--' . $long : '-' . $short);
        $this->ValueType = $this->IsFlag ? ($multipleAllowed ? CliOptionValueType::INTEGER : CliOptionValueType::BOOLEAN) : $valueType;
        $this->Delimiter = $multipleAllowed && !$this->IsFlag ? Str::coalesce($delimiter, null) : null;
        $this->Description = $description;
        $this->AllowedValues = $this->IsOneOf ? $allowedValues : null;
        $this->UnknownValuePolicy = $this->IsOneOf ? $unknownValuePolicy : null;
        $this->AddAll = $this->IsOneOf ? $addAll && $multipleAllowed && $allowedValues : false;
        $this->DefaultValue = $this->IsFlag ? ($multipleAllowed ? 0 : false) : ($multipleAllowed ? $this->maybeSplitValue($defaultValue) : $defaultValue);
        $this->OriginalDefaultValue = $this->DefaultValue;
        $this->Nullable = $nullable;
        $this->ValueCallback = $valueCallback;
        $this->Visibility = $hide ? CliOptionVisibility::NONE : $visibility;

        if ($inSchema) {
            $this->Visibility |= CliOptionVisibility::SCHEMA;
        }

        if (func_num_args() >= 22) {
            $this->BindTo = &$bindTo;
            $this->IsBound = true;
        } else {
            $this->IsBound = false;
        }
    }

    /**
     * Prepare the option for use with a command
     *
     * @return static
     */
    public function load()
    {
        if ($this->IsLoaded) {
            // @codeCoverageIgnoreStart
            return $this;
            // @codeCoverageIgnoreEnd
        }

        $clone = clone $this;
        $clone->doLoad();
        $clone->IsLoaded = true;
        return $clone;
    }

    private function doLoad(): void
    {
        if ($this->IsPositional) {
            if ($this->Name === null) {
                throw new LogicException("At least one of 'name' and 'long' must be set");
            }

            if ($this->Long !== null && $this->Name !== $this->Long) {
                throw new LogicException("'name' and 'long' must have the same value when both are applied to a positional option");
            }
        } else {
            if ($this->Long === null && $this->Short === null) {
                throw new LogicException("At least one of 'long' and 'short' must be set");
            }

            if ($this->Long !== null && !Pcre::match(self::LONG_REGEX, $this->Long)) {
                throw new LogicException("'long' must start with a letter, number or underscore, followed by one or more letters, numbers, underscores or hyphens");
            }

            if ($this->Short !== null && !Pcre::match(self::SHORT_REGEX, $this->Short)) {
                throw new LogicException("'short' must contain one letter, number or underscore");
            }
        }

        if (
            $this->ValueOptional &&
            ($this->DefaultValue === null || $this->DefaultValue === []) &&
            !($this->MultipleAllowed && $this->Nullable)
        ) {
            throw new LogicException("'defaultValue' cannot be empty when value is optional");
        }

        if (is_array($this->DefaultValue)) {
            if (!$this->checkValueTypes($this->DefaultValue)) {
                throw new LogicException(sprintf("'defaultValue' must be a value, or an array of values, of type %s", $this->getValueTypeName()));
            }
        } elseif ($this->DefaultValue !== null && !$this->IsFlag) {
            if (!$this->checkValueType($this->DefaultValue)) {
                throw new LogicException(sprintf("'defaultValue' must be a value of type %s", $this->getValueTypeName()));
            }
        }

        if ($this->EnvVariable !== null && Env::has($this->EnvVariable)) {
            $value = Env::get($this->EnvVariable);
            if ($this->IsFlag) {
                if ($this->MultipleAllowed && Test::isIntValue($value) && (int) $value >= 0) {
                    $this->DefaultValue = (int) $value;
                } elseif (Test::isBoolValue($value)) {
                    $value = Get::boolean($value);
                    $this->DefaultValue = $this->MultipleAllowed ? (int) $value : $value;
                } else {
                    $this->throwEnvVariableException($value);
                }
            } elseif ($this->MultipleAllowed) {
                $values = $this->maybeSplitValue($value);
                if (!$this->checkValueTypes($values)) {
                    $this->throwEnvVariableException($value);
                }
                $this->DefaultValue = $values;
            } else {
                if (!$this->checkValueType($value)) {
                    $this->throwEnvVariableException($value);
                }
                $this->DefaultValue = $value;
            }
            if ($this->DefaultValue !== []) {
                $this->Required = false;
            }
        }

        if (!$this->IsOneOf) {
            return;
        }

        if (
            !$this->AllowedValues ||
            !$this->checkValueTypes($this->AllowedValues)
        ) {
            throw new LogicException(sprintf("'allowedValues' must be an array of values of type %s", $this->getValueTypeName()));
        }

        if (count(Arr::unique($this->AllowedValues)) !== count($this->AllowedValues)) {
            throw new LogicException("Values in 'allowedValues' must be unique");
        }

        if ($this->ValueType === CliOptionValueType::STRING) {
            $lower = Arr::lower($this->AllowedValues);
            if (count(Arr::unique($lower)) === count($this->AllowedValues)) {
                $this->CaseSensitive = false;
                $this->AllowedValues = array_combine($lower, $this->AllowedValues);
            }
        }

        if ($this->AddAll) {
            if ($this->CaseSensitive) {
                $values = array_diff($this->AllowedValues, ['ALL']);
                if ($values === $this->AllowedValues) {
                    $this->AllowedValues[] = 'ALL';
                }
            } else {
                $values = $this->AllowedValues;
                unset($values['all']);
                if ($values === $this->AllowedValues) {
                    $this->AllowedValues['all'] = 'ALL';
                }
            }

            if (!$values) {
                throw new LogicException("'allowedValues' must have at least one value other than 'ALL'");
            }

            if (
                $this->DefaultValue && (
                    Arr::same($this->DefaultValue, $values) ||
                    in_array('ALL', $this->DefaultValue, true)
                )
            ) {
                $this->DefaultValue = ['ALL'];
            }
        }

        if ($this->OriginalDefaultValue !== null) {
            try {
                $this->filterValue(
                    $this->OriginalDefaultValue,
                    "'defaultValue'",
                    CliOptionValueUnknownPolicy::REJECT
                );
            } catch (CliUnknownValueException $ex) {
                throw new LogicException(Str::upperFirst($ex->getMessage()));
            }
        }

        if (
            $this->EnvVariable !== null &&
            $this->DefaultValue !== null &&
            $this->DefaultValue !== $this->OriginalDefaultValue
        ) {
            $this->DefaultValue = $this->filterValue(
                $this->DefaultValue,
                sprintf("environment variable '%s'", $this->EnvVariable)
            );
        }
    }

    /**
     * @return never
     */
    private function throwEnvVariableException(string $value): void
    {
        throw new CliInvalidArgumentsException(sprintf(
            "invalid %s value in environment variable '%s' (expected%s %s): %s",
            $this->DisplayName,
            $this->EnvVariable,
            $this->MultipleAllowed ? ' list of' : '',
            $this->getValueTypeName(),
            $value,
        ));
    }

    /**
     * Get the option's JSON Schema
     *
     * @return array{description?:string,type?:string[]|string,enum?:array<string|int|bool|null>,items?:array{type?:string[]|string,enum?:array<string|int|bool|null>},uniqueItems?:bool,default?:array<string|int|bool>|string|int|bool}
     */
    public function getJsonSchema(): array
    {
        $summary = $this->getSummary();
        if ($summary !== null) {
            $schema['description'][] = $summary;
        }

        if ($this->IsOneOf) {
            $type['enum'] = $this->normaliseForSchema(array_values($this->AllowedValues));
        } else {
            $type['type'][] = CliOptionValueType::toJsonSchemaType($this->ValueType);
        }

        if ($this->ValueOptional) {
            if ($this->ValueType !== CliOptionValueType::BOOLEAN) {
                if (isset($type['enum'])) {
                    $type['enum'][] = true;
                } else {
                    $type['type'][] = 'boolean';
                }
                $types[] = 'true';
            }
            if (isset($type['enum'])) {
                $type['enum'][] = null;
            } else {
                $type['type'][] = 'null';
            }
            $types[] = 'null';
            $schema['description'][] = sprintf(
                'The %s applied if %s is: %s',
                $this->getValueName(),
                implode(' or ', $types),
                Format::value($this->DefaultValue),
            );
        }

        if (isset($type['type'])) {
            $type['type'] = Arr::unwrap($type['type']);
        }

        if ($this->MultipleAllowed && !$this->IsFlag) {
            $schema['type'] = 'array';
            $schema['items'] = $type;
            if ($this->Unique) {
                $schema['uniqueItems'] = true;
            }
        } else {
            $schema ??= [];
            $schema += $type;
        }

        if (
            $this->OriginalDefaultValue !== null &&
            $this->OriginalDefaultValue !== []
        ) {
            $schema['default'] = $this->ValueOptional
                ? false
                : $this->normaliseForSchema($this->OriginalDefaultValue);
        }

        if (isset($schema['description'])) {
            $schema['description'] = implode(' ', $schema['description']);
        }

        return $schema;
    }

    /**
     * Get the option's names
     *
     * @api
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return Arr::unique(Arr::whereNotNull([
            $this->Name,
            $this->Long,
            $this->Short,
        ]));
    }

    /**
     * Get the option's value name as lowercase, space-separated words
     */
    public function getValueName(): string
    {
        if ($this->ValueName === null) {
            return '';
        }

        return Str::lower(Str::toWords($this->ValueName));
    }

    /**
     * Get the option's value name
     *
     * - If {@see CliOption::$ValueName} contains one or more angle brackets, it
     *   is returned as-is, e.g. `<key>=<value>`
     * - If it contains uppercase characters and no lowercase characters, it is
     *   converted to kebab-case and capitalised, e.g. `VALUE-NAME`
     * - Otherwise, it is converted to kebab-case and enclosed between angle
     *   brackets, e.g. `<value-name>`
     *
     * If `$withMarkup` is `true`, capitalised value names are enclosed between
     * angle brackets, e.g. `<VALUE-NAME>`.
     *
     * In conversions to kebab-case, `=` is preserved.
     */
    public function formatValueName(bool $withMarkup = true): string
    {
        if ($this->ValueName === null) {
            return '';
        }

        if (strpbrk($this->ValueName, '<>') !== false) {
            return $this->ValueName;
        }

        $name = Str::toKebabCase($this->ValueName, '=');

        if (
            strpbrk($this->ValueName, Char::ALPHABETIC_UPPER) !== false &&
            strpbrk($this->ValueName, Char::ALPHABETIC_LOWER) === false
        ) {
            $name = Str::upper($name);
            if (!$withMarkup) {
                return $name;
            }
        }

        return '<' . $name . '>';
    }

    /**
     * Get the option's allowed values
     *
     * Example: `" (one or more of: first,last)"`
     *
     * Returns an empty string if the option doesn't have allowed values.
     *
     * @param string $format `"{}"` is replaced with a delimited list of values,
     * and if {@see CliOption::$MultipleAllowed} is `true`, `"?"` is replaced
     * with `" or more"`.
     */
    public function formatAllowedValues(string $format = ' (one? of: {})'): string
    {
        if (!$this->AllowedValues) {
            return '';
        }

        $delimiter = Str::coalesce(
            $this->MultipleAllowed ? $this->Delimiter : null,
            ','
        );

        return str_replace(
            ['?', '{}'],
            [
                $this->MultipleAllowed ? ' or more' : '',
                implode($delimiter, $this->AllowedValues),
            ],
            $format
        );
    }

    /**
     * Get the first paragraph of the option's description, unwrapping any line
     * breaks
     *
     * @api
     */
    public function getSummary(bool $withFullStop = true): ?string
    {
        if ($this->Description === null) {
            return null;
        }
        $desc = ltrim($this->Description);
        $desc = Str::setEol($desc);
        $desc = explode("\n\n", $desc, 2)[0];
        $desc = rtrim($desc);
        if ($desc === '') {
            return null;
        }
        if ($withFullStop && strpbrk($desc[-1], '.!?') === false) {
            $desc .= '.';
        }
        return Pcre::replace('/\s+/', ' ', $desc);
    }

    /**
     * If a value is a non-empty string, split it on the option's delimiter,
     * otherwise wrap it in an array if needed
     *
     * If `$value` is `null` or an empty string, an empty array is returned.
     *
     * @template T of string|int|bool
     *
     * @param T[]|T|null $value
     * @return T[]
     */
    public function maybeSplitValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }
        if ($this->Delimiter !== null && is_string($value)) {
            return explode($this->Delimiter, $value);
        }
        return [$value];
    }

    /**
     * Normalise a value, assign it to the option's bound variable, and return
     * it to the caller
     *
     * @param array<string|int|bool>|string|int|bool|null $value
     * @param bool $normalise `false` if `$value` has already been normalised.
     * @param bool $expand If `true` and the option has an optional value,
     * expand `null` or `true` to the default value of the option. Ignored if
     * `$normalise` is `false`.
     * @return mixed
     */
    public function applyValue($value, bool $normalise = true, bool $expand = false)
    {
        if ($normalise) {
            $value = $this->normaliseValue($value, $expand);
        }
        if ($this->IsBound) {
            $this->BindTo = $value;
        }
        return $value;
    }

    /**
     * If the option has a callback, apply it to a value, otherwise convert the
     * value to the option's value type
     *
     * @param array<string|int|bool>|string|int|bool|null $value
     * @param bool $expand If `true` and the option has an optional value,
     * expand `null` or `true` to the default value of the option.
     * @return mixed
     */
    public function normaliseValue($value, bool $expand = false)
    {
        if ($expand &&
            $this->ValueOptional &&
            ($value === null ||
                ($this->ValueType !== CliOptionValueType::BOOLEAN &&
                    $value === true))) {
            $value = $this->DefaultValue;
        }

        if ($value === null) {
            return $this->Nullable
                ? null
                : ($this->OptionType === CliOptionType::FLAG
                    ? ($this->MultipleAllowed ? 0 : false)
                    : ($this->MultipleAllowed ? [] : null));
        }

        if ($this->AllowedValues) {
            $value = $this->filterValue($value);
            if ($this->AddAll && in_array('ALL', Arr::wrap($value), true)) {
                $value = array_values(array_diff($this->AllowedValues, ['ALL']));
            }
        } else {
            if (is_array($value)) {
                if ($this->IsFlag && (
                    !$this->MultipleAllowed || Arr::unique($value) !== [true]
                )) {
                    $this->throwValueTypeException($value);
                } elseif (!$this->MultipleAllowed) {
                    throw new CliInvalidArgumentsException(
                        sprintf('%s does not accept multiple values', $this->DisplayName)
                    );
                }
            }
            if ($this->IsFlag && $this->MultipleAllowed && !is_int($value)) {
                if (is_array($value)) {
                    $value = count($value);
                } elseif (Test::isBoolValue($value)) {
                    $value = Get::boolean($value) ? 1 : 0;
                }
            }
        }

        if ($this->ValueCallback) {
            $this->maybeCheckUnique($value);
            return ($this->ValueCallback)($value);
        }

        if (!is_array($value)) {
            return $this->normaliseValueType($value);
        }

        foreach ($value as &$_value) {
            $_value = $this->normaliseValueType($_value);
        }
        $this->maybeCheckUnique($value);
        return $value;
    }

    /**
     * If the option has allowed values, check a given value is valid and apply
     * the option's unknown value policy if not
     *
     * @template T of array<string|int|bool>|string|int|bool|null
     *
     * @param T $value
     * @param CliOptionValueUnknownPolicy::*|null $policy Overrides the option's
     * unknown value policy if given.
     * @return T
     */
    private function filterValue($value, ?string $source = null, ?int $policy = null)
    {
        $policy ??= $this->UnknownValuePolicy;

        if (
            $value === null ||
            $value === '' ||
            $value === [] ||
            !$this->AllowedValues ||
            ($this->CaseSensitive && $policy === CliOptionValueUnknownPolicy::ACCEPT)
        ) {
            return $value;
        }

        $value = $this->maybeSplitValue($value);

        if (!$this->CaseSensitive) {
            $normalised = [];
            foreach ($value as $value) {
                $lower = Str::lower((string) $value);
                $normalised[] = $this->AllowedValues[$lower] ?? $value;
            }
            $value = $normalised;
        }

        if ($policy !== CliOptionValueUnknownPolicy::ACCEPT) {
            $invalid = array_diff($value, $this->AllowedValues);
            if ($invalid) {
                // "invalid --field values 'title','name' (expected one of: first,last)"
                $message = Inflect::format(
                    $invalid,
                    'invalid %s {{#:value}} %s%s%s',
                    $this->DisplayName,
                    "'" . implode("','", $invalid) . "'",
                    $source !== null ? " in $source" : '',
                    $this->formatAllowedValues(' (expected one? of: {})'),
                );

                if ($policy !== CliOptionValueUnknownPolicy::DISCARD) {
                    throw new CliUnknownValueException($message);
                }

                Console::message(Level::WARNING, '__Warning:__', $message, MessageType::UNFORMATTED);
                $value = array_intersect($value, $this->AllowedValues);
            }
        }

        return $this->MultipleAllowed ? $value : Arr::first($value);
    }

    /**
     * Normalise a value for inclusion in a help message
     *
     * @param string|int|bool|null $value
     */
    public function normaliseValueForHelp($value): string
    {
        if (
            $this->ValueType === CliOptionValueType::BOOLEAN &&
            !$this->IsFlag &&
            $value !== null
        ) {
            $value = Get::boolean($value);
            return Format::yn($value);
        }
        return (string) $value;
    }

    /**
     * @param array<string|int|bool>|string|int|bool $value
     * @return array<string|int|bool>|string|int|bool
     */
    private function normaliseForSchema($value)
    {
        if (
            $this->ValueType === CliOptionValueType::DATE ||
            $this->ValueCallback
        ) {
            return $value;
        }

        $values = (array) $value;
        foreach ($values as &$_value) {
            $_value = $this->normaliseValueType($_value, false);
        }
        return is_array($value) ? $values : reset($values);
    }

    /**
     * @param string|int|bool $value
     * @return DateTimeImmutable|string|int|bool
     */
    private function normaliseValueType($value, bool $checkPathExists = true)
    {
        if (!$this->checkValueType($value)) {
            $this->throwValueTypeException($value);
        }

        switch ($this->ValueType) {
            case CliOptionValueType::BOOLEAN:
                return Get::boolean($value);

            case CliOptionValueType::INTEGER:
                return (int) $value;

            case CliOptionValueType::STRING:
                return (string) $value;

            case CliOptionValueType::DATE:
                return new DateTimeImmutable($value);

            case CliOptionValueType::PATH:
                return $this->checkPath($value, $checkPathExists, 'path', 'file_exists');

            case CliOptionValueType::FILE:
                return $this->checkPath($value, $checkPathExists, 'file', 'is_file');

            case CliOptionValueType::DIRECTORY:
                return $this->checkPath($value, $checkPathExists, 'directory', 'is_dir');

            case CliOptionValueType::PATH_OR_DASH:
                return $this->checkPath($value, $checkPathExists, 'path', 'file_exists', true);

            case CliOptionValueType::FILE_OR_DASH:
                return $this->checkPath($value, $checkPathExists, 'file', 'is_file', true);
        }
    }

    /**
     * @template T of string|int|bool
     *
     * @param T $value
     * @param callable(string): bool $callback
     * @return T
     */
    private function checkPath($value, bool $checkExists, string $fileType, callable $callback, bool $dashOk = false)
    {
        if ($dashOk && $value === '-') {
            return $value;
        }
        if ($checkExists && !$callback((string) $value)) {
            throw new CliInvalidArgumentsException(
                sprintf('%s not found: %s', $fileType, $value)
            );
        }
        return $value;
    }

    /**
     * @param array<string|int|bool> $values
     */
    private function checkValueTypes(array $values): bool
    {
        foreach ($values as $value) {
            if (!$this->checkValueType($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string|int|bool $value
     */
    private function checkValueType($value): bool
    {
        switch ($this->ValueType) {
            case CliOptionValueType::BOOLEAN:
                return Test::isBoolValue($value);

            case CliOptionValueType::INTEGER:
                return Test::isIntValue($value);

            case CliOptionValueType::STRING:
                return is_scalar($value);

            case CliOptionValueType::DATE:
                return Test::isDateString($value);

            case CliOptionValueType::PATH:
            case CliOptionValueType::FILE:
            case CliOptionValueType::DIRECTORY:
            case CliOptionValueType::PATH_OR_DASH:
            case CliOptionValueType::FILE_OR_DASH:
                return is_string($value);

            default:
                return false;
        }
    }

    /**
     * @param array<string|int|bool>|string|int|bool $value
     * @return never
     */
    private function throwValueTypeException($value): void
    {
        throw new CliInvalidArgumentsException(sprintf(
            'invalid %s value (%s expected): %s',
            $this->DisplayName,
            $this->getValueTypeName(),
            Get::code($value),
        ));
    }

    private function getValueTypeName(): string
    {
        return CliOptionValueType::toName($this->ValueType);
    }

    /**
     * @param array<string|int|bool>|string|int|bool|null $value
     */
    private function maybeCheckUnique($value): void
    {
        if ($value === null || $value === '' || $value === [] || !$this->Unique) {
            return;
        }

        $value = $this->maybeSplitValue($value);

        if (count(Arr::unique($value)) !== count($value)) {
            throw new CliInvalidArgumentsException(
                sprintf('%s does not accept the same value multiple times', $this->DisplayName)
            );
        }
    }
}
