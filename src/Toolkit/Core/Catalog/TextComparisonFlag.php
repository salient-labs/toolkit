<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Text comparison flags
 *
 * {@see TextComparisonFlag} and {@see TextComparisonAlgorithm} values combine
 * to form one bitmask and cannot intersect.
 *
 * @extends AbstractEnumeration<int>
 */
class TextComparisonFlag extends AbstractEnumeration
{
    /**
     * Normalise values before comparing them
     */
    public const NORMALISE = 64;
}
