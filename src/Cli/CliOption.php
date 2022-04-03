<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Assert;
use Lkrms\Convert;
use Lkrms\Template\IClassCache;
use Lkrms\Template\IGettable;
use Lkrms\Template\TConstructible;
use Lkrms\Template\TGettable;
use RuntimeException;
use UnexpectedValueException;

/**
 *
 * @package Lkrms
 */
class CliOption implements IClassCache, IGettable
{
    use TConstructible, TGettable;

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
     *
     * @var string|null
     */
    protected $ValueName;

    /**
     * @var string|null
     */
    protected $Description;

    /**
     * @var array<int, string>|null
     */
    protected $AllowedValues;

    /**
     * @var string
     */
    protected $DefaultValue;

    protected $Value;

    /**
     * @var bool
     */
    protected $IsValueSet = false;

    /**
     *
     * @param string|null   $long           e.g. `dest`
     * @param string|null   $short          e.g. `d`
     * @param string|null   $valueName      e.g. `DIR`
     * @param string|null   $description    e.g. `Sync files to DIR`
     * @param int           $optionType     e.g. {@see CliOptionType::VALUE}
     * @param array<int,string>|null    $allowedValues  For {@see CliOptionType::ONE_OF}
     * @param bool          $required
     * @param bool          $multipleAllowed
     * @param string|array<int,string>|null $defaultValue
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
    )
    {
        $this->Long  = $long ?: null;
        $this->Short = $short ?: null;

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

    public function validate()
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

    public function setValue($value)
    {
        if ($this->IsValueSet)
        {
            throw new RuntimeException("Value already set: {$this->DisplayName}");
        }

        $this->Value      = $value;
        $this->IsValueSet = true;
    }
}

