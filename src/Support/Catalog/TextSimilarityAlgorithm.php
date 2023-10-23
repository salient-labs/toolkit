<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Text similarity algorithms
 *
 * @extends Enumeration<int>
 */
class TextSimilarityAlgorithm extends Enumeration
{
    /**
     * Inexpensive, but string length cannot exceed 255 characters
     *
     * {@see TextSimilarityAlgorithm::SIMILAR_TEXT} may match substrings better.
     */
    public const LEVENSHTEIN = 0;

    /**
     * Expensive, but strings of any length can be compared
     */
    public const SIMILAR_TEXT = 1;
}
