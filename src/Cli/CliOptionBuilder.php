<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;

/**
 * A fluent interface for creating CliOption objects
 *
 * @method static $this build(?IContainer $container = null) Create a new CliOptionBuilder (syntactic sugar for 'new CliOptionBuilder()')
 * @method $this long(?string $value) See {@see CliOption::$Long}
 * @method $this short(?string $value) See {@see CliOption::$Short}
 * @method $this valueName(?string $value) See {@see CliOption::$ValueName}
 * @method $this description(?string $value) See {@see CliOption::$Description}
 * @method $this optionType(int $value) A {@see CliOptionType} value (see {@see CliOption::$OptionType})
 * @method $this allowedValues(string[]|null $value) Ignored unless `$optionType` is {@see CliOptionType::ONE_OF} or {@see CliOptionType::ONE_OF_OPTIONAL} (see {@see CliOption::$AllowedValues})
 * @method $this required(bool $value = true)
 * @method $this multipleAllowed(bool $value = true) See {@see CliOption::$MultipleAllowed}
 * @method $this defaultValue(string|string[]|bool|int|null $value) See {@see CliOption::$DefaultValue}
 * @method $this envVariable(?string $value) Use the value of environment variable `$envVariable`, if set, instead of `$defaultValue`
 * @method $this delimiter(?string $value) If `$multipleAllowed` is set, use `$delimiter` to split one value into multiple values (see {@see CliOption::$Delimiter})
 * @method $this valueCallback(?callable $value) See {@see CliOption::$ValueCallback}
 * @method CliOption go() Return a new CliOption object
 * @method static CliOption resolve(CliOption|CliOptionBuilder $object) Resolve a CliOptionBuilder or CliOption object to a CliOption object
 *
 * @uses CliOption
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Cli\CliOption' --static-builder='build' --terminator='go' --static-resolver='resolve'
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
}
