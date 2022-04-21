<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Assert;
use Lkrms\Convert;
use Lkrms\Template\IAccessible;
use Lkrms\Template\IConstructible;
use Lkrms\Template\IGettable;
use Lkrms\Template\TConstructible;
use Lkrms\Template\TGettable;
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
 * @property-read string|null $ValueName
 * @property-read string|null $Description
 * @property-read string[]|null $AllowedValues
 * @property-read string|string[]|null $DefaultValue
 * @property-read string|string[]|bool|int|null $Value
 * @property-read bool $IsValueSet
 *
 * @package Lkrms
 */
class CliOption implements IConstructible, IGettable
{
    use TConstructible, TGettable;

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
     *
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
     * @var string|string[]|null
     */
    protected $DefaultValue;

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
     * @internal
     * @return array
     */
    public static function getGettable(): array
    {
        return IAccessible::ALLOW_PROTECTED;
    }

    /**
     * Create a new command-line option
     *
     * @param string|null $long e.g. `dest`
     * @param string|null $short e.g. `d`
     * @param string|null $valueName e.g. `DIR`
     * @param string|null $description e.g. `Sync files to DIR`
     * @param int $optionType e.g. {@see CliOptionType::VALUE}
     * @param string[]|null $allowedValues For {@see CliOptionType::ONE_OF} and
     * {@see CliOptionType::ONE_OF_OPTIONAL}
     * @param bool $required
     * @param bool $multipleAllowed
     * @param string|string[]|null $defaultValue
     * @see TConstructible::from()
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
        $defaultValue         = null
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
            $this->DefaultValue    = false;

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
    }

    /**
     * @internal
     * @return void
     * @throws UnexpectedValueException
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
            Assert::pregMatch($this->Long, "/^[a-z0-9][-a-z0-9_]+\$/i", "long");
        }

        if (!is_null($this->Short))
        {
            Assert::pregMatch($this->Short, "/^[a-z0-9]\$/i", "short");
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
     * @return void
     * @throws RuntimeException
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
