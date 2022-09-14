<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Concern\TConstructible;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IConstructible;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Assert;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\ArrayMapperFlag;
use Psr\Container\ContainerInterface as Container;
use UnexpectedValueException;

/**
 * A getopt-style command line option
 *
 * See {@see CliCommand::_getOptions()} for more information.
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
 * @property-read string|null $EnvironmentVariable
 * @property-read string|string[]|bool|int|null $Value
 */
final class CliOption implements IConstructible, IReadable, IImmutable
{
    use TConstructible, TFullyReadable
    {
        TConstructible::from as private _from;
        TConstructible::listFrom as private _listFrom;
    }

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
    protected $IsFlag = false;

    /**
     * @internal
     * @var bool
     */
    protected $IsPositional = false;

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
     * @param int $optionType A {@see CliOptionType} value.
     * @param string[]|null $allowedValues Ignored unless `$optionType` is
     * {@see CliOptionType::ONE_OF} or {@see CliOptionType::ONE_OF_OPTIONAL}.
     * @param string|string[]|bool|int|null $defaultValue
     * @param string|null $envVariable Use the value of environment variable
     * `$envVariable`, if set, instead of `$defaultValue`.
     * @param string|null $delimiter If `$multipleAllowed` is set, use
     * `$delimiter` to split one value into multiple values.
     */
    public function __construct(?string $long, ?string $short, ?string $valueName, ?string $description, int $optionType = CliOptionType::FLAG, ?array $allowedValues = null, bool $required = false, bool $multipleAllowed = false, $defaultValue = null, ?string $envVariable = null, ?string $delimiter = ",")
    {
        $this->Long            = $long ?: null;
        $this->OptionType      = $optionType;
        $this->MultipleAllowed = $multipleAllowed;
        $this->Delimiter       = $multipleAllowed ? $delimiter : null;
        $this->Description     = $description;

        switch ($optionType)
        {
            case CliOptionType::FLAG:
                $this->IsFlag  = true;
                $required      = false;
                $valueRequired = false;
                $defaultValue  = $this->MultipleAllowed ? 0 : false;
                break;
            case CliOptionType::VALUE_POSITIONAL:
                $this->IsPositional = true;
                $short         = null;
                $key           = $this->Long;
                $displayName   = $this->Long;
                $required      = true;
                $valueRequired = true;
                $valueName     = $valueName ?: strtoupper(Convert::toSnakeCase($this->Long));
                break;
            case CliOptionType::ONE_OF:
            case CliOptionType::ONE_OF_OPTIONAL:
                $this->AllowedValues = $allowedValues;
            default:
                $this->EnvironmentVariable = $envVariable ?: null;
                if ($this->EnvironmentVariable && Env::has($envVariable))
                {
                    $required     = false;
                    $defaultValue = Env::get($envVariable);
                }
                break;
        }

        $this->Short           = $short ?: null;
        $this->Key             = $key ?? ($this->Short . "|" . $this->Long);
        $this->DisplayName     = $displayName ?? ($this->Long ? "--" . $this->Long : "-" . $this->Short);
        $this->IsRequired      = $required;
        $this->IsValueRequired = $valueRequired ?? !in_array($optionType, [CliOptionType::VALUE_OPTIONAL, CliOptionType::ONE_OF_OPTIONAL]);
        $this->ValueName       = $valueName ?: "VALUE";
        $this->DefaultValue    = $this->IsRequired ? null : $defaultValue;

        if ($this->Delimiter && $this->DefaultValue && is_string($this->DefaultValue))
        {
            $this->DefaultValue = explode($this->Delimiter, $this->DefaultValue);
        }
    }

    /**
     * @internal
     * @see CliCommand::applyOption()
     */
    public function validate(): void
    {
        if ($this->IsPositional && is_null($this->Long))
        {
            throw new UnexpectedValueException("long must be set");
        }
        elseif (!$this->IsPositional && is_null($this->Long) && is_null($this->Short))
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
    public function withValue($value): self
    {
        $clone        = clone $this;
        $clone->Value = $value;

        return $clone;
    }

    /**
     * Use a fluent interface to create a new CliOption object
     *
     * See {@see CliCommand::_getOptions()} for more information.
     *
     */
    public static function getBuilder(): CliOptionBuilder
    {
        return new CliOptionBuilder();
    }

    /**
     * @deprecated
     */
    public static function from(?Container $container, array $data, ? callable $callback = null, ?array $keyMap = null, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED, $parent = null)
    {
        return self::_from($container, $data, $callback, $keyMap, $conformity, $flags, $parent);
    }

    /**
     * @deprecated
     */
    public static function listFrom(?Container $container, iterable $list, ? callable $callback = null, ?array $keyMap = null, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED, $parent = null): iterable
    {
        return self::_listFrom($container, $list, $callback, $keyMap, $conformity, $flags, $parent);
    }

}
