<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IDictionary;

/**
 * Base class for dictionaries
 *
 * @template TValue
 *
 * @extends Catalog<TValue>
 * @implements IDictionary<TValue>
 */
abstract class Dictionary extends Catalog implements IDictionary
{
    /**
     * @inheritDoc
     */
    public static function definitions(): array
    {
        return self::constants();
    }
}
