<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\DictionaryInterface;

/**
 * Base class for dictionaries
 *
 * @api
 *
 * @template TValue
 *
 * @extends AbstractCatalog<TValue>
 * @implements DictionaryInterface<TValue>
 */
abstract class AbstractDictionary extends AbstractCatalog implements DictionaryInterface
{
    /**
     * @inheritDoc
     */
    public static function definitions(): array
    {
        return self::constants();
    }
}
