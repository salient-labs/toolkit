<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Assert;
use Lkrms\Convert;
use Lkrms\Test;
use Exception;

class CliOption
{
    public $Long;

    public $Short;

    public $Key;

    public $DisplayName;

    public $IsFlag;

    public $IsRequired;

    public $IsValueRequired;

    public $MultipleAllowed;

    public $ValueName;

    public $Description;

    public $AllowedValues;

    public $DefaultValue;

    private static function ScalarToString($value, string $errorMessage): string
    {
        if (is_scalar($value))
        {
            return (string)$value;
        }
        else
        {
            throw new Exception($errorMessage);
        }
    }

    public function __construct( ? string $long, ? string $short, ? string $valueName, ? string $description, int $flags = Cli::OPTION_TYPE_FLAG, array $allowedValues = null, $defaultValue = null)
    {
        if ( ! Test::IsOneFlagSet($flags, Cli::MASK_OPTION_TYPE))
        {
            throw new Exception("Invalid option type");
        }

        $this->Long  = Convert::EmptyToNull($long);
        $this->Short = Convert::EmptyToNull($short);

        if ( ! is_null($this->Long))
        {
            Assert::PregMatch($long, "/^[a-z0-9][-a-z0-9_]+\$/i", "long");
        }

        if ( ! is_null($this->Short))
        {
            Assert::PregMatch($short, "/^[a-z0-9]\$/i", "short");
        }

        $this->Key             = $this->Short . "|" . $this->Long;
        $this->DisplayName     = $this->Long ? "--" . $this->Long : ($this->Short ? "-" . $this->Short : null);
        $this->IsFlag          = Test::IsFlagSet($flags, Cli::OPTION_TYPE_FLAG);
        $this->IsRequired      = $this->IsFlag ? false : Test::IsFlagSet($flags, Cli::OPTION_REQUIRED);
        $this->IsValueRequired = $this->IsFlag ? false : ! Test::IsFlagSet($flags, Cli::OPTION_VALUE_NOT_REQUIRED);
        $this->MultipleAllowed = Test::IsFlagSet($flags, Cli::OPTION_MULTIPLE_ALLOWED);
        $this->ValueName       = $this->IsFlag ? null : $valueName;
        $this->Description     = $description;
        $this->DefaultValue    = $this->IsFlag ? true : ($this->IsRequired ? null : $defaultValue);

        if ( ! is_null($this->DefaultValue) && ! $this->IsFlag)
        {
            if ($this->MultipleAllowed)
            {
                $this->DefaultValue = Convert::AnyToArray($this->DefaultValue);
                array_walk($this->DefaultValue,

                function ( & $value)
                {
                    $value = self::ScalarToString($value, "defaultValue must be a scalar or an array of scalars");
                }

                );
            }
            else
            {
                $this->DefaultValue = self::ScalarToString($this->DefaultValue, "defaultValue must be a scalar");
            }
        }

        if (Test::IsFlagSet($flags, Cli::OPTION_TYPE_ONE_OF))
        {
            Assert::NotEmpty($allowedValues, "allowedValues");
            $this->AllowedValues = $allowedValues;
        }
    }
}

