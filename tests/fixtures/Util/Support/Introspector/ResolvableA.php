<?php declare(strict_types=1);

namespace Lkrms\Tests\Support\Introspector;

use Lkrms\Contract\IResolvable;
use Salient\Core\Utility\Str;

class ResolvableA implements IResolvable
{
    public static function normalise(
        string $name,
        bool $greedy = true,
        string ...$hints
    ): string {
        return Str::toSnakeCase($name);
    }
}
