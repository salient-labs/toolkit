<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Normalisable;
use Salient\Contract\Core\NormaliserFactory;
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
    /** @var array<string,Closure(string $name, bool $greedy=, string...$hints): string> */
    private static $Normaliser = [];

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
        $normaliser = self::$Normaliser[static::class]
            ??= static::getNormaliser();

        return $normaliser($name, $greedy, ...$hints);
    }
}
