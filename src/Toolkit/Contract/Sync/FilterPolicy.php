<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Sync\Exception\FilterPolicyViolationExceptionInterface;

interface FilterPolicy
{
    /**
     * Ignore unclaimed filters
     *
     * The provider may return unfiltered entities, all of which are returned to
     * the caller.
     */
    public const IGNORE = 0;

    /**
     * Throw an exception if there are unclaimed filters
     *
     * A {@see FilterPolicyViolationExceptionInterface} is thrown and the
     * request is not passed to the provider.
     *
     * This is the default policy.
     */
    public const THROW_EXCEPTION = 1;

    /**
     * Return an empty result if there are unclaimed filters
     *
     * The request is not passed to the provider. An empty array (`[]`) is
     * returned to the caller for list operations, otherwise `null` is returned.
     */
    public const RETURN_EMPTY = 2;

    /**
     * Perform local filtering of entities returned by the provider
     *
     * The provider may return unfiltered entities, and any that don't match the
     * unclaimed filters are removed from the result returned to the caller.
     */
    public const FILTER = 3;
}
