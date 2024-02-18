<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Get::query() flags
 *
 * @extends AbstractEnumeration<int>
 */
final class QueryFlag extends AbstractEnumeration
{
    public const PRESERVE_LIST_KEYS = 1;

    public const PRESERVE_NUMERIC_KEYS = 2;

    public const PRESERVE_STRING_KEYS = 4;

    public const PRESERVE_ALL_KEYS =
        QueryFlag::PRESERVE_LIST_KEYS
        | QueryFlag::PRESERVE_NUMERIC_KEYS
        | QueryFlag::PRESERVE_STRING_KEYS;
}
