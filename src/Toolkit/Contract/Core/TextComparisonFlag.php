<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * Text comparison flags
 *
 * {@see TextComparisonFlag} and {@see TextComparisonAlgorithm} values combine
 * to form one bitmask and cannot intersect.
 */
interface TextComparisonFlag
{
    /**
     * Normalise values before comparing them
     */
    public const NORMALISE = 64;
}
