<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Salient\Core\Contract\Normalisable;
use Salient\Core\Contract\NormaliserFactory;
use Salient\Core\Utility\Str;
use Closure;

/**
 * Implements NormaliserFactory and Normalisable
 *
 * @see NormaliserFactory
 * @see Normalisable
 *
 * @phpstan-require-implements NormaliserFactory
 * @phpstan-require-implements Normalisable
 */
trait HasNormaliser
{
    /**
     * @var array<string,Closure(string $name, bool $greedy=, string...$hints): string>
     */
    private static $_Normaliser = [];

    /**
     * @inheritDoc
     */
    public static function getNormaliser(): Closure
    {
        return fn(string $name): string =>
            Str::toSnakeCase($name);
    }

    /**
     * @inheritDoc
     */
    final public static function normalise(
        string $name,
        bool $greedy = true,
        string ...$hints
    ): string {
        $normaliser = self::$_Normaliser[static::class]
            ??= static::getNormaliser();

        return $normaliser($name, $greedy, ...$hints);
    }
}
