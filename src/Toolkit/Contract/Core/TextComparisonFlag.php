<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use Salient\Core\AbstractEnumeration;

/**
 * Text comparison flags
 *
 * {@see TextComparisonFlag} and {@see TextComparisonAlgorithm} values combine
 * to form one bitmask and cannot intersect.
 *
 * @extends AbstractEnumeration<int>
 */
final class TextComparisonFlag extends AbstractEnumeration
{
    /**
     * Normalise values before comparing them
     */
    public const NORMALISE = 64;
}
