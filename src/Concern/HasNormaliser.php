<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IResolvable;
use Lkrms\Utility\Convert;
use Closure;

/**
 * Implements IResolvable
 *
 * @see IResolvable
 */
trait TResolvable
{
    /**
     * @var array<string,Closure(string, bool=, string...): string>
     */
    private static $_Normaliser = [];

    public static function normaliser(): Closure
    {
        return fn(string $name): string => Convert::toSnakeCase($name);
    }

    final public static function normalise(string $name, bool $greedy = true, string ...$hints): string
    {
        $normaliser = self::$_Normaliser[static::class]
            ?? (self::$_Normaliser[static::class] = static::normaliser());

        return $normaliser($name, $greedy, ...$hints);
    }
}
