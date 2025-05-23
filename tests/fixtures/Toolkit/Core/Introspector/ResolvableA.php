<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Contract\Core\Entity\Normalisable;
use Salient\Utility\Str;

class ResolvableA implements Normalisable
{
    public static function normaliseProperty(
        string $name,
        bool $fromData = true,
        string ...$declaredName
    ): string {
        return Str::snake($name);
    }
}
