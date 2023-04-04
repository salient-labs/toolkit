<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Closure;
use Lkrms\Facade\Convert;

/**
 * Implements IResolvable
 *
 * @see \Lkrms\Contract\IResolvable
 */
trait TResolvable
{
    /**
     * @var array<string,Closure>
     */
    private static $_Normaliser = [];

    public static function normaliser(): Closure
    {
        return fn(string $name): string => Convert::toSnakeCase($name);
    }

    final public static function normalise(string $name, bool $greedy = true, string ...$hints): string
    {
        return ((self::$_Normaliser[static::class] ?? null)
            ?: (self::$_Normaliser[static::class] = static::normaliser()))($name, $greedy, ...$hints);
    }
}
