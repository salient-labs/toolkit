<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Concern\TConstructible;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IConstructible;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Assert;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use RuntimeException;
use UnexpectedValueException;

/**
 * A command-line option accepted by a CliCommand
 *
 * See {@see CliCommand::_getOptions()} for more information.
 *
 * @property-read string|null $Long
 * @property-read string|null $Short
 * @property-read string $Key
 * @property-read string $DisplayName
 * @property-read int $OptionType
 * @property-read bool $IsFlag
 * @property-read bool $IsRequired
 * @property-read bool $IsValueRequired
 * @property-read bool $MultipleAllowed
 * @property-read string|null $Delimiter
 * @property-read string|null $ValueName
 * @property-read string|null $Description
 * @property-read string[]|null $AllowedValues
 * @property-read string|string[]|bool|int|null $DefaultValue
 * @property-read string|null $EnvironmentVariable
 * @property-read string|string[]|bool|int|null $Value
 * @property-read bool $IsValueSet
 */
class CliOption implements IConstructible, IReadable
{
    use TConstructible, TFullyReadable;

    /**
     * @internal
     * @var string|null
     */
    protected $Long;

    /**
     * @internal
     * @var string|null
     */
    protected $Short;

    /**
     * @internal
     * @var string
     */
    protected $Key;

    /**
     * @internal
     * @var string
     */
    protected $DisplayName;

    /**
     * @internal
     * @var int
     */
    protected $OptionType;

    /**
     * @internal
     * @var bool
     */
    protected $IsFlag;

    /**
     * @internal
     * @var bool
     */
    protected $IsRequired;

    /**
     * @internal
     * @var bool
     */
    protected $IsValueRequired;

    /**
     * @internal
     * @var bool
     */
    protected $MultipleAllowed;

    /**
     * @internal
     * @var string|null
     */
    protected $Delimiter;

    /**
     * @internal
     * @var string|null
     */
    protected $ValueName;

    /**
     * @internal
     * @var string|null
     */
    protected $Description;

    /**
     * @internal
     * @var string[]|null
     */
    protected $AllowedValues;

    /**
     * @internal
     * @var string|string[]|bool|int|null
     */
    protected $DefaultValue;

    /**
     * @internal
     * @var string|null
     */
    protected $EnvironmentVariable;

    /**
     * @internal
     * @var string|string[]|bool|int|null
     */
    protected $Value;

    /**
     * @internal
     * @var bool
     */
    protected $IsValueSet = false;

    /**
     * @param string|null $long
     * @param string|null $short
     * @param string|null $valueName
     * @param string|null $description
     * @param int $optionType The value of a {@see CliOptionType} constant.
     * @param string[]|null $allowedValues Ignored unless `$optionType` is
     * {@see CliOptionType::ONE_OF} or {@see CliOptionType::ONE_OF_OPTIONAL}.
     * @param bool $required
     * @param bool $multipleAllowed
     * @param string|string[]|bool|int|null $defaultValue
     * @param string|null $env Use the value of environment variable `$env`, if
     * set, instead of `$defaultValue`.
     * @param string|null $delimiter If `$multipleAllowed` is set, use
     * `$delimiter` to split one value into multiple values.
     * @see \Lkrms\Concern\TConstructible::from()
     */
    public function __construct(
        ?string $long,
        ?string $short,
        ?string $valueName,
        ?string $description,
        int $optionType       = CliOptionType::FLAG,
        array $allowedValues  = null,
        bool $required        = false,
        bool $multipleAllowed = false,
        $defaultValue         = null,
        string $env           = null,
        ?string $delimiter    = ","
    ) {
        $this->Long            = $long ?: null;
        $this->Short           = $short ?: null;
        $this->Key             = $this->Short . "|" . $this->Long;
        $this->DisplayName     = $this->Long ? "--" . $this->Long : "-" . $this->Short;
        $this->OptionType      = $optionType;
        $this->MultipleAllowed = $multipleAllowed;
        $this->Description     = $description;

        if ($this->IsFlag = ($optionType == CliOptionType::FLAG))
        {
            $this->IsRequired      = false;
            $this->IsValueRequired = false;
            $this->DefaultValue    = $this->MultipleAllowed ? 0 : false;

            return;
        }
        elseif (in_array($optionType, [CliOptionType::ONE_OF, CliOptionType::ONE_OF_OPTIONAL]))
        {
            $this->AllowedValues = $allowedValues;
        }

        $this->IsRequired      = $required;
        $this->IsValueRequired = !in_array($optionType, [CliOptionType::VALUE_OPTIONAL, CliOptionType::ONE_OF_OPTIONAL]);
        $this->ValueName       = $valueName ?: "VALUE";
        $this->DefaultValue    = $this->IsRequired ? null : $defaultValue;

        if (($this->EnvironmentVariable = $env) && Env::has($env))
        {
            $this->IsRequired   = false;
            $this->DefaultValue = Env::get($env);
        }

        if ($this->MultipleAllowed &&
            ($this->Delimiter = $delimiter) &&
            $this->DefaultValue && is_string($this->DefaultValue))
        {
            $this->DefaultValue = explode($this->Delimiter, $this->DefaultValue);
        }
    }

    /**
     * @internal
     * @see CliCommand::addOption()
     */
    public function validate(): void
    {
        if (is_null($this->Long) && is_null($this->Short))
        {
            throw new UnexpectedValueException("At least one must be set: long, short");
        }

        if (!is_null($this->Long))
        {
            Assert::patternMatches($this->Long, "/^[a-z0-9][-a-z0-9_]+\$/i", "long");
        }

        if (!is_null($this->Short))
        {
            Assert::patternMatches($this->Short, "/^[a-z0-9]\$/i", "short");
        }

        if (!is_null($this->DefaultValue))
        {
            if ($this->MultipleAllowed)
            {
                $this->DefaultValue = Convert::toArray($this->DefaultValue);
                array_walk($this->DefaultValue,
                    function (&$value)
                    {
                        if (($default = Convert::scalarToString($value)) === false)
                        {
                            throw new UnexpectedValueException("defaultValue must be a scalar or an array of scalars");
                        }

                        $value = $default;
                    });
            }
            else
            {
                if (($default = Convert::scalarToString($this->DefaultValue)) === false)
                {
                    throw new UnexpectedValueException("defaultValue must be a scalar");
                }

                $this->DefaultValue = $default;
            }
        }

        if (in_array($this->OptionType, [CliOptionType::ONE_OF, CliOptionType::ONE_OF_OPTIONAL]))
        {
            Assert::notEmpty($this->AllowedValues, "allowedValues");
        }
    }

    /**
     * @internal
     * @param string|string[]|bool|int|null $value
     * @see CliCommand::loadOptionValues()
     */
    public function setValue($value): void
    {
        if ($this->IsValueSet)
        {
            throw new RuntimeException("Value already set: {$this->DisplayName}");
        }

        $this->Value      = $value;
        $this->IsValueSet = true;
    }
}
