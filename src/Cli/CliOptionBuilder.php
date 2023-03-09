<?php declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;

/**
 * A fluent interface for creating CliOption objects
 *
 * @method static $this build(?IContainer $container = null) Create a new CliOptionBuilder (syntactic sugar for 'new CliOptionBuilder()')
 * @method $this long(?string $value) The name of the long form of the option
 * @method $this short(?string $value) The letter or number of the short form of the option
 * @method $this valueName(?string $value) The name of the option's value as it appears in usage information / help pages (see {@see CliOption::$ValueName})
 * @method $this description(?string $value) A description of the option (see {@see CliOption::$Description})
 * @method $this optionType(int $value) One of the CliOptionType::* values (see {@see CliOption::$OptionType})
 * @method $this allowedValues(string[]|null $value) A list of the option's possible values (see {@see CliOption::$AllowedValues})
 * @method $this required(bool $value = true) True if the option is required (default: false)
 * @method $this multipleAllowed(bool $value = true) True if the option may be given more than once (default: false)
 * @method $this defaultValue(string|string[]|bool|int|null $value) Assigned to the option if no value is set on the command line
 * @method $this envVariable(?string $value) The name of an environment variable that, if set, overrides the option's default value
 * @method $this keepDefault(bool $value = true) True if the option's environment and/or user-supplied values extend DefaultValue instead of replacing it (default: false)
 * @method $this keepEnv(bool $value = true) True if the option's user-supplied values extend the value of EnvVariable instead of replacing it (default: false)
 * @method $this delimiter(?string $value) The separator between values passed to the option as a single argument (see {@see CliOption::$Delimiter})
 * @method $this valueCallback(?callable $value) Applied to the option's value immediately before it is assigned
 * @method bool isset(string $name) True if a value for $name has been applied to the unresolved CliOption by calling $name()
 * @method CliOption go() Get a new CliOption object
 * @method static CliOption|null resolve(CliOption|CliOptionBuilder|null $object) Resolve a CliOptionBuilder or CliOption object to a CliOption object
 *
 * @uses CliOption
 * @lkrms-generate-command lk-util generate builder --static-builder=build --value-checker=isset --terminator=go --static-resolver=resolve 'Lkrms\Cli\CliOption'
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
     * @return $this
     */
    public function bindTo(&$variable)
    {
        return $this->getWithReference(__FUNCTION__, $variable);
    }
}
