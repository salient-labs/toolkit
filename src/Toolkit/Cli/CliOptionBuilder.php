<?php declare(strict_types=1);

namespace Salient\Cli;

use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Contract\Cli\CliOptionValueUnknownPolicy;
use Salient\Contract\Cli\CliOptionVisibility;
use Salient\Core\AbstractBuilder;

/**
 * A fluent CliOption factory
 *
 * @method $this name(string|null $value) The name of the option (ignored if not positional; must start with a letter, number or underscore, followed by one or more letters, numbers, underscores or hyphens)
 * @method $this long(string|null $value) The long form of the option, e.g. "verbose" (ignored if positional and name is given; must start with a letter, number or underscore, followed by one or more letters, numbers, underscores or hyphens)
 * @method $this short(string|null $value) The short form of the option, e.g. "v" (ignored if positional; must contain one letter, number or underscore)
 * @method $this valueName(?string $value) The name of the option's value as it appears in usage information
 * @method $this description(?string $value) A description of the option
 * @method $this optionType(CliOptionType::* $value) The option's type
 * @method $this valueType(CliOptionValueType::* $value) The data type of the option's value
 * @method $this allowedValues(array<string|int|bool>|null $value) The option's possible values, indexed by lowercase value if not case-sensitive
 * @method $this unknownValuePolicy(CliOptionValueUnknownPolicy::* $value) The action taken if an unknown value is given
 * @method $this required(bool $value = true) True if the option is mandatory (default: false)
 * @method $this multipleAllowed(bool $value = true) True if the option may be given more than once (default: false)
 * @method $this unique(bool $value = true) True if the same value may not be given more than once (default: false)
 * @method $this addAll(bool $value = true) True if "ALL" should be added to the list of possible values when the option can be given more than once (default: false)
 * @method $this defaultValue(array<string|int|bool>|string|int|bool|null $value) Assigned to the option if no value is given on the command line
 * @method $this nullable(bool $value = true) True if the option's value should be null if it is not given on the command line (default: false)
 * @method $this envVariable(string|null $value) The name of a value in the environment that replaces the option's default value (ignored if positional)
 * @method $this delimiter(?string $value) The separator between values passed to the option as a single argument
 * @method $this valueCallback((callable(array<string|int|bool>|string|int|bool): mixed)|null $value) Applied to the option's value as it is assigned (see {@see CliOption::$ValueCallback})
 * @method $this visibility(int-mask-of<CliOptionVisibility::*> $value) The option's visibility to users
 * @method $this inSchema(bool $value = true) True if the option should be included when generating a JSON Schema (default: false)
 * @method $this hide(bool $value = true) True if the option's visibility should be {@see CliOptionVisibility::NONE} (default: false)
 * @method CliOption load() Prepare the option for use with a command
 *
 * @api
 *
 * @extends AbstractBuilder<CliOption>
 *
 * @generated
 */
final class CliOptionBuilder extends AbstractBuilder
{
    /**
     * @internal
     */
    protected static function getService(): string
    {
        return CliOption::class;
    }

    /**
     * @internal
     */
    protected static function getTerminators(): array
    {
        return [
            'load',
        ];
    }

    /**
     * Bind the option's value to a variable
     *
     * @param mixed $variable
     * @return static
     */
    public function bindTo(&$variable)
    {
        return $this->withRefB(__FUNCTION__, $variable);
    }
}
