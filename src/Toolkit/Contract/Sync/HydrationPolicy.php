<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Core\AbstractEnumeration;

/**
 * Policies for hydration of sync entities and relationships
 *
 * @extends AbstractEnumeration<int>
 */
final class HydrationPolicy extends AbstractEnumeration
{
    /**
     * Do not perform hydration
     *
     * Uninitialised relationship properties are ignored.
     */
    public const SUPPRESS = 0;

    /**
     * Perform hydration on demand
     *
     * Relationships are hydrated when they are accessed.
     */
    public const LAZY = 1;

    /**
     * Defer hydration until deferred entities are resolved
     *
     * The {@see DeferralPolicy} applied to the context is also applied to
     * hydration.
     */
    public const DEFER = 2;

    /**
     * Perform hydration on load
     *
     * Relationships are hydrated synchronously when entities are created.
     */
    public const EAGER = 3;
}
