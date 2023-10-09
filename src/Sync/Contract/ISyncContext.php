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
 * @extends IProviderContext<ISyncProvider,ISyncEntity>
 */
interface ISyncContext extends IProviderContext
{
    /**
     * Normalise non-mandatory sync operation arguments and add them to the
     * context
     *
     * If, after removing the operation's mandatory arguments from `$args`, the
     * remaining values match one of the following signatures, they are mapped
     * to an associative array surfaced by {@see ISyncContext::getFilters()},
     * {@see ISyncContext::getFilter()} and {@see ISyncContext::claimFilter()}:
     *
     * - One array argument (`fn(...$mandatoryArgs, array $filter)`)
     *
     *   - Alphanumeric keys are converted to snake_case
     *   - Keys containing characters other than letters, numbers, hyphens and
     *     underscores, e.g. `'$orderby'`, are left as-is
     *   - {@see ISyncEntity} objects are replaced with their respective IDs
     *   - Empty keys (`''` after snake_case conversion) are invalid
     *
     * - A list of identifiers (`fn(...$mandatoryArgs, int|string ...$ids)`)
     *
     *   - Converted to `[ 'id' => $ids ]`
     *
     * - A list of entities (`fn(...$mandatoryArgs, ISyncEntity ...$entities)`)
     *
     *   - Entities are grouped by snake_case
     *     {@see \Lkrms\Contract\IProvidable::service()} basename and replaced
     *     with their IDs, e.g. `['faculty' => [42, 71], 'user' => [101]]`
     *
     * - No arguments (`fn(...$mandatoryArgs)`)
     *
     *   - Converted to an empty array (`[]`)
     *
     * If `$args` doesn't match any of these, a
     * {@see SyncInvalidFilterException} is thrown.
     *
     * Using {@see ISyncContext::claimFilter()} to claim filters is recommended.
     * Depending on the provider's {@see SyncFilterPolicy}, unclaimed filters
     * may cause requests to fail.
     *
     * When a filter is claimed, it is removed from the context.
     * {@see ISyncContext::getFilters()} and {@see ISyncContext::getFilter()}
     * only return unclaimed filters.
     *
     * @param SyncOperation::* $operation
     * @param mixed ...$args Sync operation arguments, NOT including the
     * {@see ISyncContext} argument.
     * @return $this
     */
    public function withArgs($operation, ...$args);

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
    public function withDeferredSyncEntityPolicy($policy);

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
     * @return mixed `null` if the value has been claimed via
     * {@see ISyncContext::claimFilter()} or wasn't passed to the operation.
     *
     * @see ISyncContext::withArgs()
     */
    public function getFilter(string $key);

    /**
     * Get a value from the filter most recently passed via optional sync
     * operation arguments
     *
     * Unlike other {@see ISyncContext} methods,
     * {@see ISyncContext::claimFilter()} modifies the object it is called on
     * instead of returning a modified clone.
     *
     * @return mixed `null` if the value has already been claimed or wasn't
     * passed to the operation.
     *
     * @see ISyncContext::withArgs()
     */
    public function claimFilter(string $key);

    /**
     * Get the deferred sync entity policy applied to the context
     *
     * @return DeferredSyncEntityPolicy::*
     */
    public function getDeferredSyncEntityPolicy();
}
