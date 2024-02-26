<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Core\Contract\Normalisable;
use Salient\Core\Utility\Str;

class ResolvableA implements Normalisable
{
    public static function normalise(
        string $name,
        bool $greedy = true,
        string ...$hints
    ): string {
        return Str::toSnakeCase($name);
    }
}
