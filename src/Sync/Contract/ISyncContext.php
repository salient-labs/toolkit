<?php

declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Contract\IProviderContext;

/**
 * The context within which a SyncEntity is instantiated
 *
 */
interface ISyncContext extends IProviderContext
{
    /**
     * Request arrays instead of generators from sync operations that return
     * lists
     *
     * {@see ISyncContext::getListToArray()} returns `true` until
     * {@see ISyncContext::withGenerators()} is called.
     *
     * @return $this
     */
    public function withListArrays();

    /**
     * Request generators (instead of arrays) from sync operations that return
     * lists
     *
     * @return $this
     */
    public function withGenerators();

    /**
     * Convert non-mandatory sync operation arguments to a normalised filter and
     * add it to the context
     *
     * If, after removing the operation's mandatory arguments from `$args`, the
     * remaining values have one of the following signatures, they are mapped to
     * an associative array returned by {@see ISyncContext::getFilter()} until
     * the next call to {@see ISyncContext::withArgs()}.
     *
     * 1. One array argument (`fn(array $filter)`)
     *    - Alphanumeric keys are converted to snake_case
     *    - Keys containing characters other than letters, numbers, hyphens and
     *      underscores, e.g. `'$orderby'`, are left as-is
     *
     * 2. A list of entity IDs (`fn(int|string ...$ids)`)
     *    - Converted to `[ "id" => $ids ]`
     *
     * 3. A list of entities (`fn(SyncEntity ...$entities)`)
     *    - Converted to an array that maps the normalised name of each entity's
     *      unqualified {@see \Lkrms\Contract\IProvidable::service()} to an
     *      array of entity IDs
     *
     * If none of these match `$args`, {@see ISyncContext::getFilter()} returns
     * `null`. {@see ISyncContext::getFilter()} returns an empty array (`[]`) if
     * no non-mandatory arguments were provided.
     *
     * Using {@see ISyncContext::claimFilterValue()} to "claim" values from the
     * filter is recommended. Depending on the provider's
     * {@see \Lkrms\Sync\Support\SyncFilterPolicy}, unclaimed values may cause
     * requests to fail.
     *
     * {@see \Lkrms\Sync\Concept\SyncEntity} objects are replaced with the value
     * of their {@see \Lkrms\Sync\Concept\SyncEntity::$Id Id} when `$args`
     * contains an array or a list of entities. This operation is not recursive.
     *
     * @return $this
     */
    public function withArgs(int $operation, ...$args);

    /**
     * Return true if list operations should return arrays instead of generators
     *
     * @return bool
     */
    public function getListToArray(): bool;

    /**
     * Get the filter most recently passed via optional sync operation arguments
     *
     * @return array|null `null` if arguments were passed to the operation but
     * couldn't be converted to a filter.
     * @see ISyncContext::withArgs()
     */
    public function getFilter(): ?array;

    /**
     * Get a value from the filter most recently passed via optional sync
     * operation arguments
     *
     * Unlike other {@see ISyncContext} methods,
     * {@see ISyncContext::claimFilterValue()} modifies the object it is called
     * on instead of returning a modified clone.
     *
     * @return mixed `null` if the value has already been claimed or wasn't
     * passed to the operation.
     * @see ISyncContext::withArgs()
     */
    public function claimFilterValue(string $key);

}
