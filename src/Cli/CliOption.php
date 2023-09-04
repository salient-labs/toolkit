<?php declare(strict_types=1);

namespace Lkrms\Cli;

use DateTimeImmutable;
use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\Catalog\CliOptionValueUnknownPolicy;
use Lkrms\Cli\Catalog\CliOptionVisibility;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\Exception\CliUnknownValueException;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleMessageType as MessageType;
use Lkrms\Contract\HasBuilder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Assert;
use Lkrms\Facade\Console;
use Lkrms\Support\Catalog\CharacterSequence as Char;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;
use Lkrms\Utility\Test;
use LogicException;
use Throwable;

/**
 * A getopt-style command line option
 *
 * @property-read string|null $Long The long form of the option, e.g. 'verbose'
 * @property-read string|null $Short The short form of the option, e.g. 'v'
 * @property-read string $Key The option's internal identifier
 * @property-read string|null $ValueName The name of the option's value as it appears in usage information
 * @property-read string $DisplayName The option's name as it appears in usage information
 * @property-read CliOptionType::* $OptionType The option's type
 * @property-read CliOptionValueType::* $ValueType The data type of the option's value
 * @property-read bool $IsFlag True if the option is a flag
 * @property-read bool $IsOneOf True if the option accepts values from a list
 * @property-read bool $IsPositional True if the option is positional
 * @property-read bool $Required True if the option is mandatory
 * @property-read bool $ValueRequired True if the option has a mandatory value
 * @property-read bool $ValueOptional True if the option has an optional value
 * @property-read bool $MultipleAllowed True if the option may be given more than once
 * @property-read string|null $Delimiter The separator between values passed to the option as a single argument
 * @property-read string|null $Description A description of the option
 * @property-read array<string|int>|null $AllowedValues A list of the option's possible values
 * @property-read CliOptionValueUnknownPolicy::*|null $UnknownValuePolicy The action taken if an unknown value is given
 * @property-read bool $AddAll True if 'ALL' should be added to the list of possible values when the option can be given more than once
 * @property-read array<string|int>|string|int|bool|null $DefaultValue Assigned to the option if no value is given on the command line
 * @property-read array<string|int>|string|int|bool|null $OriginalDefaultValue The default value passed to the option's constructor
 * @property-read bool $KeepDefault True if environment- and/or user-supplied values extend the option's default value instead of replacing it
 * @property-read string|null $EnvVariable The name of an environment variable that replaces or extends the option's default value
 * @property-read bool $KeepEnv True if user-supplied values extend values from the environment instead of replacing them
 * @property-read callable|null $ValueCallback Applied to the option's value as it is assigned
 * @property-read int-mask-of<CliOptionVisibility::*> $Visibility The option's visibility to users
 */
final class CliOption implements HasBuilder, IImmutable, IReadable
{
    use TFullyReadable;

    /**
     * The long form of the option, e.g. 'verbose'
     *
     * Must start with a letter, number or underscore, followed by one or more
     * letters, numbers, underscores or hyphens.
     *
     * @var string|null
     */
    protected $Long;

    /**
     * The short form of the option, e.g. 'v'
     *
     * Must contain one letter, number or underscore.
     *
     * @var string|null
     */
    protected $Short;

    /**
     * The option's internal identifier
     *
     * @var string
     */
    protected $Key;

    /**
     * The name of the option's value as it appears in usage information
     *
     * @var string|null
     * @see CliOption::getFriendlyValueName()
     */
    protected $ValueName;

    /**
     * The option's name as it appears in usage information
     *
     * @var string
     */
    protected $DisplayName;

    /**
     * The option's type
     *
     * One of the {@see CliOptionType} values.
     *
     * @var CliOptionType::*
     */
    protected $OptionType;

    /**
     * The data type of the option's value
     *
     * One of the {@see CliOptionValueType} values.
     *
     * @var CliOptionValueType::*
     */
    protected $ValueType;

    /**
     * True if the option is a flag
     *
     * @var bool
     */
    protected $IsFlag;

    /**
     * True if the option accepts values from a list
     *
     * @var bool
     */
    protected $IsOneOf;

    /**
     * True if the option is positional
     *
     * @var bool
     */
    protected $IsPositional;

    /**
     * True if the option is mandatory
     *
     * @var bool
     */
    protected $Required;

    /**
     * True if the option has a mandatory value
     *
     * @var bool
     */
    protected $ValueRequired;

    /**
     * True if the option has an optional value
     *
     * @var bool
     */
    protected $ValueOptional;

    /**
     * True if the option may be given more than once
     *
     * @var bool
     */
    protected $MultipleAllowed;

    /**
     * The separator between values passed to the option as a single argument
     *
     * Ignored if {@see CliOption::$MultipleAllowed} is `false`.
     *
     * @var string|null
     */
    protected $Delimiter;

    /**
     * A description of the option
     *
     * Blank lines, newlines after two spaces, and lines with four leading
     * spaces are preserved during formatting.
     *
     * @var string|null
     */
    protected $Description;

    /**
     * A list of the option's possible values
     *
     * Ignored if {@see CliOption::$IsOneOf} is `false`.
     *
     * @var array<string|int>|null
     */
    protected $AllowedValues;

    /**
     * The action taken if an unknown value is given
     *
     * Ignored if {@see CliOption::$IsOneOf} is `false`.
     *
     * @var CliOptionValueUnknownPolicy::*|null
     */
    protected $UnknownValuePolicy;

    /**
     * True if 'ALL' should be added to the list of possible values when the
     * option can be given more than once
     *
     * Ignored if {@see CliOption::$IsOneOf} or
     * {@see CliOption::$MultipleAllowed} are `false`.
     *
     * @var bool
     */
    protected $AddAll;

    /**
     * Assigned to the option if no value is given on the command line
     *
     * @var array<string|int>|string|int|bool|null
     */
    protected $DefaultValue;

    /**
     * The default value passed to the option's constructor
     *
     * @var array<string|int>|string|int|bool|null
     */
    protected $OriginalDefaultValue;

    /**
     * True if environment- and/or user-supplied values extend the option's
     * default value instead of replacing it
     *
     * @var bool
     */
    protected $KeepDefault;

    /**
     * The name of an environment variable that replaces or extends the option's
     * default value
     *
     * Ignored if the option is positional.
     *
     * @var string|null
     */
    protected $EnvVariable;

    /**
     * True if user-supplied values extend values from the environment instead
     * of replacing them
     *
     * Ignored if the option is positional.
     *
     * @var bool
     */
    protected $KeepEnv;

    /**
     * Applied to the option's value as it is assigned
     *
     * Providing a {@see CliOption::$ValueCallback} disables conversion of the
     * option's value to {@see CliOption::$ValueType}. The callback should
     * return a value of the expected type.
     *
     * @var callable|null
     */
    protected $ValueCallback;

    /**
     * The option's visibility to users
     *
     * @var int-mask-of<CliOptionVisibility::*>
     */
    protected $Visibility;

    /**
     * @var mixed
     */
    private $BindTo;

    /**
     * @param CliOptionType::* $optionType
     * @param CliOptionValueType::* $valueType
     * @param array<string|int>|null $allowedValues
     * @param CliOptionValueUnknownPolicy::* $unknownValuePolicy
     * @param array<string|int>|string|int|bool|null $defaultValue
     * @param int-mask-of<CliOptionVisibility::*> $visibility
     * @param bool $hide True if the option's visibility should be
     * {@see CliOptionVisibility::NONE}.
     * @param mixed $bindTo Assign user-supplied values to a variable before
     * running the command.
     */
    public function __construct(
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
        bool $addAll = false,
        $defaultValue = null,
        ?string $envVariable = null,
        bool $keepDefault = false,
        bool $keepEnv = false,
        ?string $delimiter = ',',
        ?callable $valueCallback = null,
        int $visibility = CliOptionVisibility::ALL,
        bool $hide = false,
        &$bindTo = null
    ) {
        $this->OptionType = $optionType;
        $this->IsFlag = $optionType === CliOptionType::FLAG;
        $this->IsOneOf = in_array($optionType, [CliOptionType::ONE_OF, CliOptionType::ONE_OF_OPTIONAL, CliOptionType::ONE_OF_POSITIONAL], true);
        $this->IsPositional = in_array($optionType, [CliOptionType::VALUE_POSITIONAL, CliOptionType::ONE_OF_POSITIONAL], true);
        $this->Required = $required && !$this->IsFlag;
        $this->ValueRequired = in_array($optionType, [CliOptionType::VALUE, CliOptionType::VALUE_POSITIONAL, CliOptionType::ONE_OF, CliOptionType::ONE_OF_POSITIONAL], true);
        $this->ValueOptional = in_array($optionType, [CliOptionType::VALUE_OPTIONAL, CliOptionType::ONE_OF_OPTIONAL], true);
        $this->MultipleAllowed = $multipleAllowed;
        $this->EnvVariable = $this->IsPositional ? null : ($envVariable ?: null);

        $this->Long = $long ?: null;
        $this->Short = $this->IsPositional ? null : ($short ?: null);
        $this->Key = $this->IsPositional ? $long : ($short . '|' . $long);
        $this->ValueName = $this->IsFlag ? null : ($valueName ?: ($this->IsPositional ? Convert::toSnakeCase($long, '=') : null) ?: 'value');
        $this->DisplayName = $this->IsPositional ? $this->getFriendlyValueName(false) : ($long ? '--' . $long : '-' . $short);
        $this->ValueType = $this->IsFlag ? ($multipleAllowed ? CliOptionValueType::INTEGER : CliOptionValueType::BOOLEAN) : $valueType;
        $this->Delimiter = $multipleAllowed && !$this->IsFlag ? $delimiter : null;
        $this->Description = $description;
        $this->AllowedValues = $this->IsOneOf ? $allowedValues : null;
        $this->UnknownValuePolicy = $this->IsOneOf ? $unknownValuePolicy : null;
        $this->AddAll = $this->IsOneOf ? $addAll && $multipleAllowed && $allowedValues : false;
        $this->DefaultValue = $this->Required ? null : ($this->IsFlag ? ($multipleAllowed ? 0 : false) : ($multipleAllowed ? $this->maybeSplitValue($defaultValue) : $defaultValue));
        $this->KeepDefault = $keepDefault && $this->DefaultValue && is_array($this->DefaultValue);
        $this->KeepEnv = $this->EnvVariable && ($this->KeepDefault || ($keepEnv && !$this->IsFlag && $multipleAllowed));
        $this->ValueCallback = $valueCallback;
        $this->Visibility = $hide ? CliOptionVisibility::NONE : $visibility;
        $this->BindTo = &$bindTo;
        $this->OriginalDefaultValue = $this->DefaultValue;

        if ($this->AddAll) {
            $this->AllowedValues = array_diff($this->AllowedValues, ['ALL']);
            if ($this->DefaultValue && $this->DefaultValue === $this->AllowedValues) {
                $this->DefaultValue = ['ALL'];
            }
            $this->AllowedValues[] = 'ALL';
        }

        if ($this->EnvVariable && Env::has($this->EnvVariable)) {
            if ($this->IsFlag) {
                $value = Env::getBool($this->EnvVariable);
                $this->DefaultValue = $multipleAllowed ? ($value ? 1 : 0) : $value;
            } else {
                $this->Required = false;
                $value = Env::get($this->EnvVariable);
                if ($this->KeepDefault) {
                    $this->DefaultValue = array_merge($this->DefaultValue, $this->maybeSplitValue($value));
                } elseif ($multipleAllowed) {
                    $this->DefaultValue = $this->maybeSplitValue($value);
                } else {
                    $this->DefaultValue = $value;
                }
            }
        }
    }

    /**
     * Split delimited values into an array, if possible
     *
     * @internal
     * @param array<string|int>|string|int|bool|null $value
     * @return array<string|int>
     */
    public function maybeSplitValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!$value) {
            return [];
        }
        if ($this->Delimiter && is_string($value)) {
            return explode($this->Delimiter, $value);
        }
        if (is_int($value)) {
            return [$value];
        }
        return [(string) $value];
    }

    /**
     * Get the option's defined names
     *
     * Returns `[` {@see CliOption::$Long} `,` {@see CliOption::$Short} `]`
     * after removing empty values.
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return array_values(array_filter([
            $this->Long,
            $this->Short,
        ]));
    }

    /**
     * Format the option's value name
     *
     * If {@see CliOption::$ValueName} contains angle brackets (`<`, `>`), it is
     * returned as-is, otherwise:
     *
     * - if it contains uppercase characters and no lowercase characters, it is
     *   converted to kebab-case and capitalised
     * - if not, it is converted to kebab-case and enclosed between angle
     *   brackets
     *
     * In conversions to kebab-case, `=` is preserved.
     */
    public function getFriendlyValueName(bool $withMarkup = true): ?string
    {
        if ($this->ValueName === null || strpbrk($this->ValueName, '<>') !== false) {
            return $this->ValueName;
        }

        if (strpbrk($this->ValueName, Char::ALPHABETIC_UPPER) !== false &&
                strpbrk($this->ValueName, Char::ALPHABETIC_LOWER) === false) {
            [$before, $after] = $withMarkup ? ['<', '>'] : ['', ''];
            return $before . strtoupper(Convert::toKebabCase($this->ValueName, '=')) . $after;
        }
        return '<' . Convert::toKebabCase($this->ValueName, '=') . '>';
    }

    /**
     * @internal
     * @return $this
     * @see CliCommand::applyOption()
     */
    public function validate()
    {
        if ($this->IsPositional) {
            if ($this->Long === null) {
                throw new LogicException("'long' must be set");
            }
        } elseif ($this->Long === null && $this->Short === null) {
            throw new LogicException("At least one of 'long' and 'short' must be set");
        }

        if ($this->Long !== null &&
                !preg_match($regex = '/^[a-z0-9_][-a-z0-9_]+$/i', $this->Long)) {
            throw new LogicException(sprintf("'long' must match pattern '%s'", $regex));
        }

        if ($this->Short !== null &&
                !preg_match($regex = '/^[a-z0-9_]$/i', $this->Short)) {
            throw new LogicException(sprintf("'short' must match pattern '%s'", $regex));
        }

        if ($this->Required && !$this->ValueRequired) {
            throw new LogicException("'required' must be false when value is optional");
        }

        if ($this->DefaultValue !== null && !$this->IsFlag) {
            if ($this->MultipleAllowed) {
                array_walk(
                    $this->DefaultValue,
                    function (&$value) {
                        if (($default = Convert::scalarToString($value)) === false) {
                            throw new LogicException("'defaultValue' must be a scalar or an array of scalars");
                        }
                        $value = $default;
                    }
                );
            } else {
                if (($default = Convert::scalarToString($this->DefaultValue)) === false) {
                    throw new LogicException("'defaultValue' must be a scalar");
                }
                $this->DefaultValue = $default;
            }
        }

        if ($this->IsOneOf) {
            Assert::notEmpty($this->AllowedValues, 'allowedValues');

            if ($this->OriginalDefaultValue) {
                try {
                    $this->OriginalDefaultValue = $this->applyUnknownValuePolicy(
                        $this->OriginalDefaultValue,
                        'defaultValue'
                    );
                } catch (CliUnknownValueException $ex) {
                    throw new LogicException("'defaultValue' must satisfy 'unknownValuePolicy'", 0, $ex);
                }
            }

            if ($this->EnvVariable &&
                    $this->DefaultValue &&
                    $this->DefaultValue !== $this->OriginalDefaultValue) {
                try {
                    $this->DefaultValue = $this->applyUnknownValuePolicy(
                        $this->DefaultValue,
                        sprintf("environment variable '%s'", $this->EnvVariable)
                    );
                } catch (CliUnknownValueException $ex) {
                    // Discard rejected values to ensure they're not displayed
                    // in usage info / help messages
                    $clone = clone $this;
                    $clone->UnknownValuePolicy = CliOptionValueUnknownPolicy::DISCARD;
                    $this->DefaultValue = $clone->applyUnknownValuePolicy($this->DefaultValue);
                    throw $ex;
                }
            }
        }

        return $this;
    }

    /**
     * If values don't appear in AllowedValues, apply the option's
     * UnknownValuePolicy
     *
     * @internal
     * @template T of array<string|int>|string|int|bool|null
     * @param T $value
     * @return T
     */
    public function applyUnknownValuePolicy($value, ?string $source = null)
    {
        if (!$this->AllowedValues) {
            return $value;
        }

        switch ($this->UnknownValuePolicy) {
            case CliOptionValueUnknownPolicy::ACCEPT:
                return $value;

            case CliOptionValueUnknownPolicy::DISCARD:
                $value = $this->maybeSplitValue($value);
                if ($invalid = array_diff($value, $this->AllowedValues)) {
                    Console::message(
                        Level::WARNING, '__Warning:__', $this->getUnknownValueMessage(
                            $invalid, $source
                        ), MessageType::UNFORMATTED
                    );
                }
                $value = array_intersect($value, $this->AllowedValues);
                return $this->MultipleAllowed
                    ? $value
                    : $value[0] ?? null;

            case CliOptionValueUnknownPolicy::REJECT:
            default:
                if ($invalid = array_diff($this->maybeSplitValue($value), $this->AllowedValues)) {
                    throw new CliUnknownValueException($this->getUnknownValueMessage($invalid, $source));
                }
                return $value;
        }
    }

    /**
     * @param array<string|int> $invalid
     */
    private function getUnknownValueMessage(array $invalid, ?string $source): string
    {
        // "invalid --field values 'title','name' (expected one of: first,last)"
        return "invalid {$this->DisplayName} "
            . Convert::plural(count($invalid), 'value') . " '" . implode("','", $invalid) . "'"
            . ($source ? " in $source" : '')
            . $this->getFriendlyAllowedValues(' (expected one? of: {})');
    }

    /**
     * " (one or more of: first,last)"
     *
     * @internal
     */
    public function getFriendlyAllowedValues(string $message = ' (one? of: {})'): string
    {
        if (!$this->AllowedValues) {
            return '';
        }
        $delimiter = ($this->MultipleAllowed ? $this->Delimiter : null) ?: ',';
        return str_replace(
            [
                '?',
                '{}',
            ],
            [
                $this->MultipleAllowed ? ' or more' : '',
                implode($delimiter, $this->AllowedValues),
            ],
            $message
        );
    }

    /**
     * True if a value is identical to the option's original default value
     *
     * `$value` is compared with the default value passed to the option's
     * constructor (and normalised by {@see CliOption::maybeSplitValue()} if
     * {@see CliOption::$MultipleAllowed} is `true`).
     *
     * @param mixed $value
     */
    public function isOriginalDefaultValue($value): bool
    {
        return $this->OriginalDefaultValue === $value;
    }

    /**
     * Normalise a value, assign it to the option's bound variable, and return
     * it to the caller
     *
     * @internal
     * @param array<string|int>|string|int|bool|null $value
     * @param bool $normalise `false` if `$value` has already been normalised.
     * @param bool $expand If `true`, replace `null` (or `true`, if the option
     * is not a flag and doesn't have type {@see CliOptionValueType::BOOLEAN})
     * with the default value of the option if it has an optional value. Ignored
     * if `$normalise` is `false`.
     * @return mixed
     * @see CliOption::normaliseValue()
     */
    public function applyValue(
        $value,
        bool $normalise = true,
        bool $expand = false
    ) {
        if ($normalise) {
            return $this->BindTo = $this->normaliseValue($value, $expand);
        }
        return $this->BindTo = $value;
    }

    /**
     * If the option has a callback, apply it to a value, otherwise convert the
     * value to the option's value type
     *
     * @internal
     * @param array<string|int>|string|int|bool|null $value
     * @param bool $expand If `true`, replace `null` (or `true`, if the option
     * is not a flag and doesn't have type {@see CliOptionValueType::BOOLEAN})
     * with the default value of the option if it has an optional value.
     * @return mixed
     * @see CliOption::$ValueType
     * @see CliOption::$ValueCallback
     */
    public function normaliseValue(
        $value,
        bool $expand = false
    ) {
        if ($expand &&
            $this->ValueOptional &&
            $this->DefaultValue !== null &&
            ($value === null ||
                ($this->ValueType !== CliOptionValueType::BOOLEAN &&
                    $value === true))) {
            $value = $this->DefaultValue;
        }

        if ($value === null) {
            return $this->OptionType === CliOptionType::FLAG
                ? ($this->MultipleAllowed ? 0 : false)
                : null;
        }

        if ($this->AllowedValues) {
            $value = $this->applyUnknownValuePolicy($value);
            if ($this->AddAll && in_array('ALL', (array) $value)) {
                $value = array_diff($this->AllowedValues, ['ALL']);
            }
        } else {
            if (is_array($value)) {
                if ($this->IsFlag &&
                        (!$this->MultipleAllowed || !Test::isArrayOfValue($value, true))) {
                    $this->throwValueTypeException(
                        $value,
                        $this->MultipleAllowed
                            ? 'integer or array<true>'
                            : 'boolean'
                    );
                } elseif (!$this->MultipleAllowed) {
                    throw new CliInvalidArgumentsException(
                        sprintf('%s does not accept multiple values', $this->DisplayName)
                    );
                }
            }
            if ($this->IsFlag && $this->MultipleAllowed && !is_int($value)) {
                $value = is_array($value)
                    ? count($value)
                    : (Test::isBoolValue($value)
                        ? (Convert::toBoolOrNull($value) ? 1 : 0)
                        : $value);
            }
        }

        if ($this->ValueCallback) {
            return ($this->ValueCallback)($value);
        }

        if (!is_array($value)) {
            return $this->normaliseValueType($value);
        }

        foreach ($value as &$_value) {
            $_value = $this->normaliseValueType($_value);
        }
        return $value;
    }

    /**
     * @param string|int|bool|null $value
     * @return mixed
     */
    private function normaliseValueType($value)
    {
        switch ($this->ValueType) {
            case CliOptionValueType::BOOLEAN:
                if (is_bool($value)) {
                    return $value;
                }
                if (!Test::isBoolValue($value)) {
                    $this->throwValueTypeException($value, 'boolean');
                }
                return Convert::toBoolOrNull($value);

            case CliOptionValueType::INTEGER:
                if (is_int($value)) {
                    return $value;
                }
                if (!Test::isIntValue($value)) {
                    $this->throwValueTypeException($value, 'integer');
                }
                return (int) $value;

            case CliOptionValueType::STRING:
                return (string) $value;

            case CliOptionValueType::DATE:
                try {
                    return new DateTimeImmutable($value);
                } catch (Throwable $ex) {
                    $this->throwValueTypeException($value, 'datetime', $ex->getMessage());
                }

            case CliOptionValueType::PATH:
                $fileType = 'path';
                $callable = 'file_exists';
            case CliOptionValueType::FILE:
                $fileType = $fileType ?? 'file';
                $callable = $callable ?? 'is_file';
            case CliOptionValueType::DIRECTORY:
                $fileType = $fileType ?? 'directory';
                $callable = $callable ?? 'is_dir';
                if (!$callable($value)) {
                    throw new CliInvalidArgumentsException(
                        sprintf('%s not found: %s', $fileType, $value)
                    );
                }
                return $value;
        }
    }

    /**
     * @param mixed $value
     * @return never
     */
    private function throwValueTypeException(
        $value,
        string $type,
        ?string $message = null
    ) {
        if ($message) {
            $message = sprintf('invalid %s value %s (%s expected): %s', $this->DisplayName, Convert::valueToCode($value), $type, $message);
        } else {
            $message = sprintf('invalid %s value %s (%s expected)', $this->DisplayName, Convert::valueToCode($value), $type);
        }
        throw new CliInvalidArgumentsException($message);
    }

    /**
     * Use a fluent interface to create a new CliOption object
     *
     */
    public static function build(?IContainer $container = null): CliOptionBuilder
    {
        return new CliOptionBuilder($container);
    }

    /**
     * @param CliOptionBuilder|CliOption $object
     */
    public static function resolve($object): CliOption
    {
        return CliOptionBuilder::resolve($object);
    }
}
