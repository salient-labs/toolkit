<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Entity\Writable;
use Salient\Core\Internal\WritePropertyTrait;

/**
 * Implements Writable
 *
 * - If `_set<Property>()` is defined, it is called instead of assigning
 *   `$value` to `<Property>`.
 * - If `_unset<Property>()` is defined, it is called to unset `<Property>`
 *   instead of assigning `null`.
 * - The existence of `_set<Property>()` makes `<Property>` writable, regardless
 *   of {@see Writable::getWritableProperties()}'s return value.
 *
 * @api
 *
 * @see Writable
 */
trait HasWritableProperties
{
    use WritePropertyTrait;

    public static function getWritableProperties(): array
    {
        return [];
    }
}
