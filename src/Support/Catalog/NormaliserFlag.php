<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Normaliser flags
 *
 * @see \Lkrms\Contract\IResolvable::normaliser()
 * @see \Lkrms\Contract\IResolvable::normalise()
 */
final class NormaliserFlag extends Enumeration
{
    /**
     * Normalise names by applying every available transformation
     *
     * This is the default.
     */
    public const GREEDY = 1;

    /**
     * Normalise names by changing them as little as possible
     */
    public const LAZY = 2;

    /**
     * If a name matches a hint passed to the normaliser, return it without
     * applying further transformations
     *
     * The name and the hint are both {@see NormaliserFlag::LAZY}-normalised for
     * comparison.
     */
    public const CAREFUL = 4;
}
