<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Concern\IsCatalog;
use Lkrms\Contract\IDictionary;

/**
 * Base class for dictionaries
 *
 * @template TValue
 *
 * @implements IDictionary<TValue>
 */
abstract class Dictionary implements IDictionary
{
    /** @use IsCatalog<TValue> */
    use IsCatalog;

    /**
     * @inheritDoc
     */
    public static function definitions(): array
    {
        return self::constants();
    }
}
