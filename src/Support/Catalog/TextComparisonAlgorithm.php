<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Text comparison algorithms
 *
 * A text comparison algorithm takes two strings as input and calculates an
 * uncertainty value between `0` and `1`, where `0` indicates the strings could
 * not be more similar, and `1` indicates they have no similarities.
 *
 * @extends Enumeration<int>
 */
class TextComparisonAlgorithm extends Enumeration
{
    /**
     * Uncertainty is 0 if values are identical, otherwise 1
     */
    public const SAME = 1;

    /**
     * Uncertainty is 0 if the longer value contains the shorter value,
     * otherwise 1
     */
    public const CONTAINS = 2;

    /**
     * Uncertainty is derived from levenshtein()
     *
     * String length cannot exceed 255 characters.
     *
     * @see \levenshtein()
     */
    public const LEVENSHTEIN = 4;

    /**
     * Uncertainty is derived from similar_text()
     *
     * @see \similar_text()
     */
    public const SIMILAR_TEXT = 8;

    /**
     * Uncertainty is derived from ngramSimilarity()
     *
     * @see \Lkrms\Utility\Compute::ngramSimilarity()
     */
    public const NGRAM_SIMILARITY = 16;

    /**
     * Uncertainty is derived from ngramIntersection()
     *
     * @see \Lkrms\Utility\Compute::ngramIntersection()
     */
    public const NGRAM_INTERSECTION = 32;

    /**
     * Combine with one or more algorithms to indicate that values should be
     * normalised before they are compared
     */
    public const NORMALISE = 64;

    public const ALL =
        TextComparisonAlgorithm::SAME
        | TextComparisonAlgorithm::CONTAINS
        | TextComparisonAlgorithm::LEVENSHTEIN
        | TextComparisonAlgorithm::SIMILAR_TEXT
        | TextComparisonAlgorithm::NGRAM_SIMILARITY
        | TextComparisonAlgorithm::NGRAM_INTERSECTION
        | TextComparisonAlgorithm::NORMALISE;
}
