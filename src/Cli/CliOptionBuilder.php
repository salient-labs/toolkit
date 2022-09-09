<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Concept\Builder;

/**
 * Provides a fluent interface to create new CliOption objects
 *
 * @method CliOption go() Return a new CliOption object
 * @method $this long(string|null $value)
 * @method $this short(string|null $value)
 * @method $this valueName(string|null $value)
 * @method $this description(string|null $value)
 * @method $this optionType(int $value)
 * @method $this allowedValues(string[]|null $value)
 * @method $this required(bool $value)
 * @method $this multipleAllowed(bool $value)
 * @method $this defaultValue(string|string[]|bool|int|null $value)
 * @method $this envVariable(string|null $value)
 * @method $this delimiter(string|null $value)
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
