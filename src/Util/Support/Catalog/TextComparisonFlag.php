<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Text comparison flags
 *
 * {@see TextComparisonFlag} and {@see TextComparisonAlgorithm} values combine
 * to form one bitmask and cannot intersect.
 *
 * @extends Enumeration<int>
 */
class TextComparisonFlag extends Enumeration
{
    /**
     * Normalise values before comparing them
     */
    public const NORMALISE = 64;
}
