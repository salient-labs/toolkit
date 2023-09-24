<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Contract\IProviderContext;
use Lkrms\Sync\Catalog\DeferredSyncEntityPolicy;
use Lkrms\Sync\Catalog\SyncFilterPolicy;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Exception\SyncInvalidFilterException;

/**
 * The context within which a sync entity is instantiated by a provider
 *
 */
interface ISyncContext extends IProviderContext
{
    /**
     * Normalise non-mandatory sync operation arguments and add them to the
     * context
     *
     * If, after removing the operation's mandatory arguments from `$args`, the
     * remaining values match one of the following signatures, they are mapped
     * to an associative array surfaced by {@see ISyncContext::getFilters()} and
     * {@see ISyncContext::claimFilter()}:
     *
     * 1. One array argument (`fn(...$mandatoryArgs, array $filter)`)
     *    - Alphanumeric keys are converted to snake_case
     *    - Keys containing characters other than letters, numbers, hyphens and
     *      underscores, e.g. `'$orderby'`, are left as-is
     *
     * 2. A list of entity IDs (`fn(...$mandatoryArgs, int|string ...$ids)`)
     *    - Converted to `[ 'id' => $ids ]`
     *
     * 3. A list of entities (`fn(...$mandatoryArgs, ISyncEntity ...$entities)`)
     *    - Converted to an array that maps the normalised name of each entity's
     *      unqualified {@see \Lkrms\Contract\IProvidable::service()} to an
     *      array of entity IDs
     *
     * 4. No arguments (`fn(...$mandatoryArgs)`)
     *    - Converted to an empty array (`[]`)
     *
     * If `$args` doesn't match any of these, a
     * {@see SyncInvalidFilterException} is thrown.
     *
     * Using {@see ISyncContext::claimFilter()} to claim filters is recommended.
     * Depending on the provider's {@see SyncFilterPolicy}, unclaimed filters
     * may cause requests to fail.
     *
     * When a filter is claimed, it is removed from the context.
     * {@see ISyncContext::getFilters()} only returns unclaimed filters.
     *
     * {@see ISyncEntity} objects are replaced with the return value of
     * {@see ISyncEntity::id()} when `$args` contains an array or a list of
     * entities. This operation is not recursive.
     *
     * @param SyncOperation::* $operation
     * @param mixed ...$args Sync operation arguments, NOT including the
     * {@see ISyncContext} argument.
     * @return $this
     */
    public function withArgs(int $operation, ...$args);

    /**
     * Use a callback to enforce the provider's unclaimed filter policy
     *
     * Allows providers to enforce their {@see SyncFilterPolicy} by calling
     * {@see ISyncContext::maybeApplyFilterPolicy()} in scenarios where
     * enforcement before a sync operation starts isn't possible.
     *
     * @see ISyncContext::maybeApplyFilterPolicy()
     *
     * @param (callable(ISyncContext, ?bool &$returnEmpty, array{}|null &$empty): void)|null $callback
     * @return $this
     */
    public function withFilterPolicyCallback(?callable $callback);

    /**
     * Apply a deferred sync entity policy to the context
     *
     * @param DeferredSyncEntityPolicy::* $policy
     * @return $this
     */
    public function withDeferredSyncEntityPolicy(int $policy);

    /**
     * Run the unclaimed filter policy callback
     *
     * Example:
     *
     * ```php
     * <?php
     * class Provider extends \Lkrms\Sync\Concept\HttpSyncProvider
     * {
     *     public function getList_Entity(\Lkrms\Sync\Contract\ISyncContext $ctx): iterable
     *     {
     *         if ($ctx->claimFilter('pending')) {
     *             $entryTypes[] = 0;
     *         }
     *         if ($ctx->claimFilter('completed')) {
     *             $entryTypes[] = 1;
     *         }
     *         $ctx->maybeApplyFilterPolicy($returnEmpty, $empty);
     *         if ($returnEmpty) {
     *             return $empty;
     *         }
     *         // ...
     *     }
     * }
     * ```
     *
     * @param array{}|null $empty
     */
    public function maybeApplyFilterPolicy(?bool &$returnEmpty, &$empty): void;

    /**
     * Get the filter most recently passed via optional sync operation arguments
     *
     * @see ISyncContext::withArgs()
     *
     * @return array<string,mixed>
     */
    public function getFilters(): array;

    /**
     * Get a value from the filter most recently passed via optional sync
     * operation arguments
     *
     * Unlike other {@see ISyncContext} methods,
     * {@see ISyncContext::claimFilter()} modifies the object it is called
     * on instead of returning a modified clone.
     *
     * @return mixed `null` if the value has already been claimed or wasn't
     * passed to the operation.
     * @see ISyncContext::withArgs()
     */
    public function claimFilter(string $key);

    /**
     * Get the deferred sync entity policy applied to the context
     *
     * @return DeferredSyncEntityPolicy::*
     */
    public function getDeferredSyncEntityPolicy(): int;
}
