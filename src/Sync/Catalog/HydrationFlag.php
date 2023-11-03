<?php declare(strict_types=1);

namespace Lkrms\Sync\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Sync entity hydration flags
 *
 * @extends Enumeration<int>
 */
final class HydrationFlag extends Enumeration
{
    /**
     * Do not perform hydration
     *
     * Uninitialised relationship properties are ignored.
     */
    public const SUPPRESS = 1;

    /**
     * Perform hydration on demand
     *
     * Relationships are hydrated when they are accessed.
     */
    public const LAZY = 2;

    /**
     * Defer hydration until deferred entities are resolved
     *
     * The {@see DeferralPolicy} applied to the context is also applied to
     * hydration.
     */
    public const DEFER = 4;

    /**
     * Perform hydration on load
     *
     * Relationships are hydrated synchronously when entities are created.
     */
    public const EAGER = 8;

    /**
     * Do not apply a filter to the context when hydrating relationships
     */
    public const NO_FILTER = 16;
}
