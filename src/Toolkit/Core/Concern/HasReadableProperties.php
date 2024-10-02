<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Entity\Readable;
use Salient\Core\Internal\ReadPropertyTrait;

/**
 * Implements Readable
 *
 * - If `_get<Property>()` is defined, it is called instead of returning the
 *   value of `<Property>`.
 * - If `_isset<Property>()` is defined, it is called instead of returning
 *   `isset(<Property>)`.
 * - The existence of `_get<Property>()` makes `<Property>` readable, regardless
 *   of {@see Readable::getReadableProperties()}'s return value.
 *
 * @api
 *
 * @see Readable
 */
trait HasReadableProperties
{
    use ReadPropertyTrait;

    public static function getReadableProperties(): array
    {
        return [];
    }
}
