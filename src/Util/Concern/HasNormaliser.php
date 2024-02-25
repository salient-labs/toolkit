<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Salient\Core\Contract\ReturnsNormaliser;
use Salient\Core\Utility\Str;
use Closure;

/**
 * Implements ReturnsNormaliser
 *
 * @see ReturnsNormaliser
 */
trait HasNormaliser
{
    /**
     * @var array<string,Closure(string $name, bool $greedy=, string...$hints): string>
     */
    private static $_Normaliser = [];

    public static function normaliser(): Closure
    {
        return
            fn(string $name): string =>
                Str::toSnakeCase($name);
    }

    final public static function normalise(string $name, bool $greedy = true, string ...$hints): string
    {
        $normaliser = self::$_Normaliser[static::class]
            ?? (self::$_Normaliser[static::class] = static::normaliser());

        return $normaliser($name, $greedy, ...$hints);
    }
}
