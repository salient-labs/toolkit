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
 * @property-read bool $Required
 * @property-read bool $ValueRequired
 * @property-read bool $MultipleAllowed
 * @property-read string|null $Delimiter
 * @property-read string|null $ValueName
 * @property-read string|null $Description
 * @property-read string[]|null $AllowedValues
 * @property-read bool $AddAll
 * @property-read string|string[]|bool|int|null $DefaultValue
 * @property-read bool $KeepDefault
 * @property-read string|null $EnvVariable
 * @property-read bool $KeepEnv
 * @property-read string|string[]|bool|int|null $Value
 * @property-read callable|null $ValueCallback
 */
final class CliOption implements IReadable, IImmutable, HasBuilder
{
    use TFullyReadable;

    /**
     * The name of the long form of the option
     *
     * @var string|null
     */
    protected $Long;

    /**
     * The letter or number of the short form of the option
     *
     * @var string|null
     */
    protected $Short;

    /**
     * The internal identifier of the option
     *
     * @var string
     */
    protected $Key;

    /**
     * The name of the option as it appears in usage information / help pages
     *
     * @var string
     */
    protected $DisplayName;

    /**
     * One of the CliOptionType::* values
     *
     * @var int
     * @phpstan-var CliOptionType::*
     * @see CliOptionType
     */
    protected $OptionType;

    /**
     * True if the option is a flag
     *
     * @var bool
     */
    protected $IsFlag;

    /**
     * True if the option is positional
     *
     * @var bool
     */
    protected $IsPositional = false;

    /**
     * True if the option is required
     *
     * @var bool
     */
    protected $Required;

    /**
     * True if the option has a required value
     *
     * @var bool
     */
    protected $ValueRequired;

    /**
     * True if the option may be given more than once
     *
     * @var bool
     */
    protected $MultipleAllowed;

    /**
     * The separator between values passed to the option as a single argument
     *
     * Ignored if {@see CliOption::$MultipleAllowed} is not set.
     *
     * @var string|null
     */
    protected $Delimiter;

    /**
     * The name of the option's value as it appears in usage information / help
     * pages
     *
     * @var string|null
     * @see CliOption::getFriendlyValueName()
     */
    protected $ValueName;

    /**
     * A description of the option
     *
     * Blank lines, newlines after two spaces, and lines with four leading
     * spaces are preserved when the description is formatted for usage
     * information / help pages.
     *
     * @var string|null
     */
    protected $Description;

    /**
     * A list of the option's possible values
     *
     * Ignored unless {@see CliOption::$OptionType} is
     * {@see CliOptionType::ONE_OF}, {@see CliOptionType::ONE_OF_OPTIONAL} or
     * {@see CliOptionType::ONE_OF_POSITIONAL}.
     *
     * @var string[]|null
     */
    protected $AllowedValues;

    /**
     * True if ALL should be added to the list of possible values when the
     * option can be given more than once
     *
     * @var bool
     */
    protected $AddAll;

    /**
     * Assigned to the option if no value is set on the command line
     *
     * @var string|string[]|bool|int|null
     */
    protected $DefaultValue;

    /**
     * True if the option's environment and/or user-supplied values extend
     * DefaultValue instead of replacing it
     *
     * @var bool
     */
    protected $KeepDefault;

    /**
     * The name of an environment variable that, if set, overrides the option's
     * default value
     *
     * @var string|null
     */
    protected $EnvVariable;

    /**
     * True if the option's user-supplied values extend the value of
     * EnvVariable instead of replacing it
     *
     * @var bool
     */
    protected $KeepEnv;

    /**
     * The value of the option after processing command line arguments,
     * EnvVariable, DefaultValue and/or ValueCallback
     *
     * @var string|string[]|bool|int|null
     */
    protected $Value;

    /**
     * Applied to the option's value immediately before it is assigned
     *
     * @var callable|null
     */
    protected $ValueCallback;

    private $BindTo;

    private $RawDefaultValue;

    /**
     * @phpstan-param CliOptionType::* $optionType
     * @param string[]|null $allowedValues
     * @param string|string[]|bool|int|null $defaultValue
     * @param $bindTo Assign user-supplied values to a variable before running
     * the command.
     */
    public function __construct(
        ?string $long,
        ?string $short,
        ?string $valueName,
        ?string $description,
        int $optionType          = CliOptionType::FLAG,
        ?array $allowedValues    = null,
        bool $required           = false,
        bool $multipleAllowed    = false,
        bool $addAll             = false,
        $defaultValue            = null,
        ?string $envVariable     = null,
        bool $keepDefault        = false,
        bool $keepEnv            = false,
        ?string $delimiter       = ',',
        ?callable $valueCallback = null,
        &$bindTo                 = null
    ) {
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
                $this->EnvVariable = $envVariable ?: null;
                if ($this->EnvVariable && Env::has($envVariable)) {
                    $required     = false;
                    $defaultValue = $this->KeepDefault
                        ? (array_merge($this->maybeSplitValue($defaultValue),
                                       $this->maybeSplitValue(Env::get($envVariable))))
                        : Env::get($envVariable);
                }
                break;
        }

        $this->Short         = $short ?: null;
        $this->Key           = $key ?? ($this->Short . '|' . $this->Long);
        $this->Required      = $required;
        $this->ValueRequired = $valueRequired ?? !in_array($optionType, [CliOptionType::VALUE_OPTIONAL, CliOptionType::ONE_OF_OPTIONAL]);
        $this->ValueName     = $this->IsFlag ? null : ($valueName ?: 'VALUE');
        $this->DisplayName   = $this->IsPositional ? $this->getFriendlyValueName() : ($this->Long ? '--' . $this->Long : '-' . $this->Short);
        $this->AddAll        = $this->AllowedValues && $this->MultipleAllowed && $addAll;
        $this->DefaultValue  = $this->Required ? null : ($this->MultipleAllowed ? $this->maybeSplitValue($defaultValue) : $defaultValue);
        $this->KeepEnv       = $this->EnvVariable && ($this->KeepDefault || ($this->MultipleAllowed && $keepEnv));
        $this->ValueCallback = $valueCallback;

        if ($this->AddAll) {
            $this->AllowedValues = array_diff($this->AllowedValues, ['ALL']);
            if ($this->DefaultValue && $this->DefaultValue === $this->AllowedValues) {
                $this->DefaultValue = ['ALL'];
            }
            $this->AllowedValues[] = 'ALL';
        }
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

        if ($this->Required && !$this->ValueRequired) {
            throw new UnexpectedValueException('required must be false when value is optional');
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
            if ($commandIsRunning && $this->EnvVariable && $this->DefaultValue &&
                    ($invalid = $this->getInvalid($this->DefaultValue))) {
                throw new CliArgumentsInvalidException(
                    $this->getInvalidMessage($invalid, "env:{$this->EnvVariable}")
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
