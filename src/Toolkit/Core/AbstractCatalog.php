<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Utility\Reflect;

/**
 * @internal
 *
 * @template TValue
 */
abstract class AbstractCatalog
{
    /**
     * @return array<string,TValue>
     */
    protected static function constants(): array
    {
        /** @var array<string,TValue> */
        return Reflect::getConstants(static::class);
    }

    final private function __construct() {}
}
