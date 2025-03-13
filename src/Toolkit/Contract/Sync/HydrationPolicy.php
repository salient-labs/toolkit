<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

interface HydrationPolicy
{
    /**
     * Do not perform hydration
     *
     * Relationships without data are ignored.
     */
    public const SUPPRESS = 0;

    /**
     * Perform hydration when deferred entities are resolved
     *
     * Relationship hydration is governed by the {@see DeferralPolicy} applied
     * to the context.
     */
    public const DEFER = 1;

    /**
     * Perform hydration on demand
     *
     * Relationships are hydrated when they are accessed.
     */
    public const LAZY = 2;

    /**
     * Perform hydration on load
     *
     * Relationships are hydrated synchronously when entities are created.
     */
    public const EAGER = 3;
}
