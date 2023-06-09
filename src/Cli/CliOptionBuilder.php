<?php declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;

/**
 * A fluent interface for creating CliOption objects
 *
 * @method static $this build(?IContainer $container = null) Create a new CliOptionBuilder (syntactic sugar for 'new CliOptionBuilder()')
 * @method $this long(?string $value) The long form of the option, e.g. 'verbose' (see {@see CliOption::$Long})
 * @method $this short(?string $value) The short form of the option, e.g. 'v' (see {@see CliOption::$Short})
 * @method $this valueName(?string $value) The name of the option's value as it appears in help messages (see {@see CliOption::$ValueName})
 * @method $this description(?string $value) A description of the option (see {@see CliOption::$Description})
 * @method $this optionType(int $value) The option's type (see {@see CliOption::$OptionType})
 * @method $this valueType(int $value) The data type of the option's value (see {@see CliOption::$ValueType})
 * @method $this allowedValues(string[]|null $value) A list of the option's possible values (see {@see CliOption::$AllowedValues})
 * @method $this unknownValuePolicy(int $value) The action taken if an unknown value is given (see {@see CliOption::$UnknownValuePolicy})
 * @method $this required(bool $value = true) True if the option is mandatory (default: false)
 * @method $this multipleAllowed(bool $value = true) True if the option may be given more than once (default: false)
 * @method $this addAll(bool $value = true) True if 'ALL' should be added to the list of possible values when the option can be given more than once (default: false; see {@see CliOption::$AddAll})
 * @method $this defaultValue(string|string[]|bool|int|null $value) Assigned to the option if no value is given on the command line
 * @method $this envVariable(?string $value) The name of an environment variable that replaces or extends the option's default value (see {@see CliOption::$EnvVariable})
 * @method $this keepDefault(bool $value = true) True if environment- and/or user-supplied values extend the option's default value instead of replacing it (default: false)
 * @method $this keepEnv(bool $value = true) True if user-supplied values extend values from the environment instead of replacing them (default: false; see {@see CliOption::$KeepEnv})
 * @method $this delimiter(?string $value) The separator between values passed to the option as a single argument (see {@see CliOption::$Delimiter})
 * @method $this valueCallback(?callable $value) Applied to the option's value as it is assigned (see {@see CliOption::$ValueCallback})
 * @method $this visibility(int $value) A bitmask of {@see CliOptionVisibility} values (see {@see CliOption::$Visibility})
 * @method $this hide(bool $value = true) True if the option's visibility should be {@see CliOptionVisibility::NONE} (default: false)
 * @method mixed get(string $name) The value of $name if applied to the unresolved CliOption by calling $name(), otherwise null
 * @method bool isset(string $name) True if a value for $name has been applied to the unresolved CliOption by calling $name()
 * @method CliOption go() Get a new CliOption object
 * @method static CliOption resolve(CliOption|CliOptionBuilder $object) Resolve a CliOptionBuilder or CliOption object to a CliOption object
 *
 * @uses CliOption
 *
 * @extends Builder<CliOption>
 */
final class CliOptionBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return CliOption::class;
    }

    /**
     * Assign user-supplied values to a variable before running the command
     *
     * @param mixed $variable
     * @return $this
     */
    public function bindTo(&$variable)
    {
        return $this->getWithReference(__FUNCTION__, $variable);
    }
}
