<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;

/**
 * A fluent interface for creating CliOption objects
 *
 * @method static $this build(?IContainer $container = null) Create a new CliOptionBuilder (syntactic sugar for 'new CliOptionBuilder()')
 * @method $this long(?string $value)
 * @method $this short(?string $value)
 * @method $this valueName(?string $value)
 * @method $this description(?string $value)
 * @method $this optionType(int $value)
 * @method $this allowedValues(string[]|null $value)
 * @method $this required(bool $value = true)
 * @method $this multipleAllowed(bool $value = true)
 * @method $this defaultValue(string|string[]|bool|int|null $value)
 * @method $this envVariable(?string $value)
 * @method $this delimiter(?string $value)
 * @method CliOption go() Return a new CliOption object
 *
 * @uses CliOption
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Cli\CliOption' --static-builder='build' --terminator='go'
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
