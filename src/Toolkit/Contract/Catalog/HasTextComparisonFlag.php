<?php declare(strict_types=1);

namespace Salient\Contract\Catalog;

/**
 * @api
 */
interface HasTextComparisonFlag
{
    /**
     * Uncertainty is 0 if values are identical, otherwise 1
     */
    public const ALGORITHM_SAME = 1;

    /**
     * Uncertainty is 0 if the longer value contains the shorter value,
     * otherwise 1
     */
    public const ALGORITHM_CONTAINS = 2;

    /**
     * Uncertainty is derived from levenshtein()
     *
     * String length cannot exceed 255 characters.
     */
    public const ALGORITHM_LEVENSHTEIN = 4;

    /**
     * Uncertainty is derived from similar_text()
     */
    public const ALGORITHM_SIMILAR_TEXT = 8;

    /**
     * Uncertainty is derived from shared ngrams relative to the longest string
     */
    public const ALGORITHM_NGRAM_SIMILARITY = 16;

    /**
     * Uncertainty is derived from shared ngrams relative to the shortest string
     */
    public const ALGORITHM_NGRAM_INTERSECTION = 32;

    /**
     * Values are normalised before comparison
     */
    public const NORMALISE = 64;
}
