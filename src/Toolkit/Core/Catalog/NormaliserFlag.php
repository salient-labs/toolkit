<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\Contract\Normalisable;
use Salient\Core\Contract\NormaliserFactory;
use Salient\Core\AbstractEnumeration;

/**
 * Normaliser flags
 *
 * @see IResolvable::normalise()
 * @see ReturnsNormaliser::normaliser()
 *
 * @extends AbstractEnumeration<int>
 */
final class NormaliserFlag extends AbstractEnumeration
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
