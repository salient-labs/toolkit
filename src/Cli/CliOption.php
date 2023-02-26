<?php declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\HasBuilder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Assert;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use UnexpectedValueException;

/**
 * A getopt-style command line option
 *
 * See {@see \Lkrms\Cli\Concept\CliCommand::getOptionList()} for more
 * information.
 *
 * @property-read string|null $Long
 * @property-read string|null $Short
 * @property-read string $Key
 * @property-read string $DisplayName
 * @property-read int $OptionType
 * @property-read bool $IsFlag
 * @property-read bool $IsPositional
 * @property-read bool $IsRequired
 * @property-read bool $IsValueRequired
 * @property-read bool $MultipleAllowed
 * @property-read string|null $Delimiter
 * @property-read string|null $ValueName
 * @property-read string|null $Description
 * @property-read string[]|null $AllowedValues
 * @property-read string|string[]|bool|int|null $DefaultValue
 * @property-read bool $KeepDefault
 * @property-read string|null $EnvironmentVariable
 * @property-read bool $KeepEnv
 * @property-read string|string[]|bool|int|null $Value
 * @property-read callable|null $ValueCallback
 */
final class CliOption implements IReadable, IImmutable, HasBuilder
{
    use TFullyReadable;

    /**
     * @var string|null
     */
    protected $Long;

    /**
     * @var string|null
     */
    protected $Short;

    /**
     * @var string
     */
    protected $Key;

    /**
     * @var string
     */
    protected $DisplayName;

    /**
     * @var int
     */
    protected $OptionType;

    /**
     * @var bool
     */
    protected $IsFlag;

    /**
     * @var bool
     */
    protected $IsPositional = false;

    /**
     * @var bool
     */
    protected $IsRequired;

    /**
     * @var bool
     */
    protected $IsValueRequired;

    /**
     * @var bool
     */
    protected $MultipleAllowed;

    /**
     * @var string|null
     */
    protected $Delimiter;

    /**
     * @var string|null
     */
    protected $ValueName;

    /**
     * @var string|null
     */
    protected $Description;

    /**
     * @var string[]|null
     */
    protected $AllowedValues;

    /**
     * @var string|string[]|bool|int|null
     */
    protected $DefaultValue;

    /**
     * @var bool
     */
    protected $KeepDefault;

    /**
     * @var string|null
     */
    protected $EnvironmentVariable;

    /**
     * @var bool
     */
    protected $KeepEnv;

    /**
     * @var string|string[]|bool|int|null
     */
    protected $Value;

    /**
     * @var callable|null
     */
    protected $ValueCallback;

    private $BindTo;

    private $RawDefaultValue;

    /**
     * @param int $optionType A {@see CliOptionType} value.
     * @psalm-param CliOptionType::* $optionType
     * @param string[]|null $allowedValues Ignored unless `$optionType` is
     * {@see CliOptionType::ONE_OF} or {@see CliOptionType::ONE_OF_OPTIONAL}.
     * @param string|string[]|bool|int|null $defaultValue
     * @param string|null $envVariable Use the value of environment variable
     * `$envVariable`, if set, instead of `$defaultValue`.
     * @param bool $keepDefault If `$multipleAllowed` is set, add environment
     * and/or user-supplied values to those in `$defaultValue`, instead of
     * replacing them. Implies `$keepEnv`.
     * @param bool $keepEnv If `$multipleAllowed` is set, add user-supplied
     * values to those in the environment, instead of replacing them.
     * @param string|null $delimiter If `$multipleAllowed` is set, use
     * `$delimiter` to split one value into multiple values.
     * @param $bindTo Assign user-supplied values to a variable before running
     * the command.
     */
    public function __construct(?string $long, ?string $short, ?string $valueName, ?string $description, int $optionType = CliOptionType::FLAG, ?array $allowedValues = null, bool $required = false, bool $multipleAllowed = false, $defaultValue = null, ?string $envVariable = null, bool $keepDefault = false, bool $keepEnv = false, ?string $delimiter = ',', ?callable $valueCallback = null, &$bindTo = null)
    {
        $this->Long            = $long ?: null;
        $this->OptionType      = $optionType;
        $this->IsFlag          = $this->OptionType === CliOptionType::FLAG;
        $this->MultipleAllowed = $multipleAllowed;
        $this->Delimiter       = $this->MultipleAllowed && !$this->IsFlag ? $delimiter : null;
        $this->Description     = $description;
        $this->KeepDefault     = $defaultValue && $this->MultipleAllowed && !$this->IsFlag && $keepDefault;
        $this->BindTo          = &$bindTo;
        $this->RawDefaultValue = $defaultValue;

        switch ($optionType) {
            case CliOptionType::FLAG:
                $required      = false;
                $valueRequired = false;
                $defaultValue  = $this->MultipleAllowed ? 0 : false;
                break;
            case CliOptionType::VALUE_POSITIONAL:
            case CliOptionType::ONE_OF_POSITIONAL:
                $this->IsPositional = true;
                $short              = null;
                $key                = $this->Long;
                $valueName          = $valueName ?: strtoupper(Convert::toSnakeCase($this->Long));
                if ($optionType === CliOptionType::ONE_OF_POSITIONAL) {
                    $this->AllowedValues = $allowedValues;
                }
                break;
            case CliOptionType::ONE_OF:
            case CliOptionType::ONE_OF_OPTIONAL:
                $this->AllowedValues = $allowedValues;
            default:
                $this->EnvironmentVariable = $envVariable ?: null;
                if ($this->EnvironmentVariable && Env::has($envVariable)) {
                    $required     = false;
                    $defaultValue = $this->KeepDefault
                        ? (array_merge($this->maybeSplitValue($defaultValue),
                                       $this->maybeSplitValue(Env::get($envVariable))))
                        : Env::get($envVariable);
                }
                break;
        }

        $this->Short           = $short ?: null;
        $this->Key             = $key ?? ($this->Short . '|' . $this->Long);
        $this->IsRequired      = $required;
        $this->IsValueRequired = $valueRequired ?? !in_array($optionType, [CliOptionType::VALUE_OPTIONAL, CliOptionType::ONE_OF_OPTIONAL]);
        $this->ValueName       = $this->IsFlag ? null : ($valueName ?: 'VALUE');
        $this->DisplayName     = $this->IsPositional ? $this->getFriendlyValueName() : ($this->Long ? '--' . $this->Long : '-' . $this->Short);
        $this->DefaultValue    = $this->IsRequired ? null : ($this->MultipleAllowed ? $this->maybeSplitValue($defaultValue) : $defaultValue);
        $this->KeepEnv         = $this->EnvironmentVariable && ($this->KeepDefault || ($this->MultipleAllowed && $keepEnv));
        $this->ValueCallback   = $valueCallback;
    }

    /**
     * Split delimited values into an array, if possible
     *
     * @internal
     * @param string|string[]|bool|int|null $value
     * @return string[]
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

        return [(string) $value];
    }

    /**
     * Format the option's value name
     *
     * For compatibility with {@link http://docopt.org docopt} and similar
     * conventions, upper-case value names are returned as-is, otherwise they
     * are converted to kebab-case and enclosed in angle brackets (`<` and `>`).
     *
     */
    public function getFriendlyValueName(): ?string
    {
        $name = $this->ValueName;
        if ($name !== strtoupper($name)) {
            $name = '<' . Convert::toKebabCase($name) . '>';
        }

        return $name;
    }

    /**
     * @internal
     * @see \Lkrms\Cli\Concept\CliCommand::applyOption()
     */
    public function validate(bool $commandIsRunning = false): void
    {
        if ($this->IsPositional) {
            if (is_null($this->Long)) {
                throw new UnexpectedValueException('long must be set');
            }
        } elseif (is_null($this->Long) && is_null($this->Short)) {
            throw new UnexpectedValueException('At least one must be set: long, short');
        }

        if (!is_null($this->Long)) {
            Assert::patternMatches($this->Long, '/^[a-z0-9][-a-z0-9_]+$/i', 'long');
        }

        if (!is_null($this->Short)) {
            Assert::patternMatches($this->Short, '/^[a-z0-9]$/i', 'short');
        }

        if (!is_null($this->DefaultValue) && !$this->IsFlag) {
            if ($this->MultipleAllowed) {
                array_walk($this->DefaultValue,
                           function (&$value) {
                               if (($default = Convert::scalarToString($value)) === false) {
                                   throw new UnexpectedValueException('defaultValue must be a scalar or an array of scalars');
                               }
                               $value = $default;
                           });
            } else {
                if (($default = Convert::scalarToString($this->DefaultValue)) === false) {
                    throw new UnexpectedValueException('defaultValue must be a scalar');
                }
                $this->DefaultValue = $default;
            }
        }

        if (in_array($this->OptionType, [
            CliOptionType::ONE_OF,
            CliOptionType::ONE_OF_OPTIONAL,
            CliOptionType::ONE_OF_POSITIONAL
        ])) {
            Assert::notEmpty($this->AllowedValues, 'allowedValues');

            if ($this->RawDefaultValue &&
                    ($invalid = $this->getInvalid($this->RawDefaultValue))) {
                throw new UnexpectedValueException($this->getInvalidMessage($invalid, 'defaultValue'));
            }
            if ($commandIsRunning && $this->EnvironmentVariable && $this->DefaultValue &&
                    ($invalid = $this->getInvalid($this->DefaultValue))) {
                throw new CliArgumentsInvalidException(
                    $this->getInvalidMessage($invalid, "env:{$this->EnvironmentVariable}")
                );
            }
        }
    }

    /**
     * Return values that don't appear in AllowedValues, if any
     *
     * @internal
     * @param string|string[]|bool|int|null $value
     * @return string[]
     * @see CliOption::$AllowedValues
     */
    public function getInvalid($value): array
    {
        if (!$this->AllowedValues) {
            return [];
        }

        return array_diff($this->maybeSplitValue($value), $this->AllowedValues);
    }

    /**
     * "invalid --field values 'title','name' (expected one of: first,last)"
     *
     * @internal
     * @param string[] $invalid
     */
    public function getInvalidMessage(array $invalid = [], ?string $source = null): string
    {
        return "invalid {$this->DisplayName}"
            . ($invalid ? ' ' . Convert::plural(count($invalid), 'value') . " '" . implode("','", $invalid) . "'" : '')
            . ($source ? " in $source" : '')
            . $this->maybeGetAllowedValues(' (expected one? of: {})');
    }

    /**
     * " (one or more of: first,last)"
     *
     * @internal
     */
    public function maybeGetAllowedValues(string $message = ' (one? of: {})'): string
    {
        if (!$this->AllowedValues) {
            return '';
        }
        $delimiter = ($this->MultipleAllowed ? $this->Delimiter : null) ?: ',';

        return str_replace([
            '?',
            '{}',
        ], [
            $this->MultipleAllowed ? ' or more' : '',
            implode($delimiter, $this->AllowedValues),
        ], $message);
    }

    /**
     * @internal
     * @param string|string[]|bool|int|null $value
     * @return $this
     * @see \Lkrms\Cli\Concept\CliCommand::loadOptionValues()
     */
    public function withValue($value)
    {
        $clone         = clone $this;
        $clone->Value  = $this->ValueCallback && !is_null($value) ? ($this->ValueCallback)($value) : $value;
        $clone->BindTo = $clone->Value;

        return $clone;
    }

    /**
     * Use a fluent interface to create a new CliOption object
     *
     * See {@see \Lkrms\Cli\Concept\CliCommand::getOptionList()} for more
     * information.
     *
     */
    public static function build(?IContainer $container = null): CliOptionBuilder
    {
        return new CliOptionBuilder($container);
    }

    /**
     * @param CliOptionBuilder|CliOption|null $object
     */
    public static function resolve($object): CliOption
    {
        return CliOptionBuilder::resolve($object);
    }
}
