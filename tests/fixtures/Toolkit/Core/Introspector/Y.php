<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Contract\Core\Entity\Relatable;

class Y implements Relatable
{
    /** @var X[] */
    public array $MyX;

    public static function getRelationships(): array
    {
        return ['MyX' => [self::ONE_TO_MANY => X::class]];
    }
}
