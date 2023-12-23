<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IProviderContext;
use Lkrms\Sync\Catalog\DeferralPolicy;
use Lkrms\Sync\Catalog\FilterPolicy;
use Lkrms\Sync\Catalog\HydrationPolicy;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Exception\SyncInvalidFilterException;

/**
 * The context within which a sync entity is instantiated by a provider
 *
 * @extends IProviderContext<ISyncProvider,ISyncEntity>
 */
interface ISyncContext extends IProviderContext
{
    /**
     * Normalise optional sync operation arguments and apply them to the context
     *
     * If, after removing the operation's mandatory arguments from `$args`, the
     * remaining values match one of the following signatures, they are mapped
     * to a filter surfaced by {@see ISyncContext::getFilters()},
     * {@see ISyncContext::getFilter()} and {@see ISyncContext::claimFilter()}:
     *
     * 1. One array argument (`fn(...$mandatoryArgs, array $filter)`)
     *
     *    - Alphanumeric keys are converted to snake_case
     *    - Keys containing characters other than letters, numbers, hyphens and
     *      underscores, e.g. `'$orderby'`, are left as-is
     *    - {@see ISyncEntity} objects are replaced with their respective IDs
     *    - Empty keys (`''` after snake_case conversion) are invalid
     *
     * 2. A list of identifiers (`fn(...$mandatoryArgs, int|string ...$ids)`)
     *
     *    - Converted to `[ 'id' => $ids ]`
     *
     * 3. A list of entities (`fn(...$mandatoryArgs, ISyncEntity ...$entities)`)
     *
     *    - Entities are grouped by snake_case {@see IProvidable::service()}
     *      basename and replaced with their IDs, e.g. `['faculty' => [42, 71],
     *      'user' => [101]]`
     *
     * 4. No arguments (`fn(...$mandatoryArgs)`)
     *
     *    - Converted to an empty array (`[]`)
     *
     * If `$args` doesn't match any of these, a
     * {@see SyncInvalidFilterException} is thrown.
     *
     * Using {@see ISyncContext::claimFilter()} to claim filters is recommended.
     * Depending on the provider's {@see FilterPolicy}, unclaimed filters may
     * cause requests to fail.
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
     * Allows providers to enforce their {@see FilterPolicy} by calling
     * {@see ISyncContext::applyFilterPolicy()} in scenarios where enforcement
     * before a sync operation starts isn't possible.
     *
     * @see ISyncContext::applyFilterPolicy()
     *
     * @param (callable(ISyncContext, ?bool &$returnEmpty, array{}|null &$empty): void)|null $callback
     * @return $this
     */
    public function withFilterPolicyCallback(?callable $callback);

    /**
     * Reject entities from the local entity store
     *
     * @return $this
     */
    public function online();

    /**
     * Reject entities from the provider
     *
     * An exception is thrown if the local entity store is unable to satisfy
     * subsequent entity requests.
     *
     * @return $this
     */
    public function offline();

    /**
     * Accept entities from the local entity store if they are sufficiently
     * fresh or if the provider cannot be reached
     *
     * This is the default behaviour.
     *
     * @return $this
     */
    public function offlineFirst();

    /**
     * Apply the given deferral policy to the context
     *
     * @param DeferralPolicy::* $policy
     * @return $this
     */
    public function withDeferralPolicy($policy);

    /**
     * Apply the given hydration policy to the context
     *
     * @param HydrationPolicy::* $policy
     * @param class-string<ISyncEntity>|null $entity Limit the scope of the
     * change to an entity and its subclasses.
     * @param array<int<1,max>>|int<1,max>|null $depth Limit the scope of the
     * change to entities at a given `$depth` from the current context.
     * @return $this
     */
    public function withHydrationPolicy(
        int $policy,
        ?string $entity = null,
        $depth = null
    );

    /**
     * Run the unclaimed filter policy callback
     *
     * {@see SyncProvider::run()} calls this method on your behalf and is
     * recommended for providers where sync operations are performed by declared
     * methods.
     *
     * Example:
     *
     * ```php
     * <?php
     * class Provider extends HttpSyncProvider
     * {
     *     public function getEntities(ISyncContext $ctx): iterable
     *     {
     *         // Claim filter values
     *         $start = $ctx->claimFilter('start_date');
     *         $end = $ctx->claimFilter('end_date');
     *
     *         // Check for violations and return `$empty` if `$returnEmpty` is true
     *         $ctx->applyFilterPolicy($returnEmpty, $empty);
     *         if ($returnEmpty) {
     *             return $empty;
     *         }
     *
     *         // Perform sync operation
     *     }
     * }
     * ```
     *
     * @param array{}|null $empty
     */
    public function applyFilterPolicy(?bool &$returnEmpty, &$empty): void;

    /**
     * Get the filters passed to the context via optional sync operation
     * arguments
     *
     * @see ISyncContext::withArgs()
     *
     * @return array<string,mixed>
     */
    public function getFilters(): array;

    /**
     * Get the value of a filter passed to the context via optional sync
     * operation arguments
     *
     * If `$orValue` is `true` and a value for `$key` has been applied to the
     * context via {@see IProviderContext::withValue()}, it is returned if there
     * is no matching filter.
     *
     * @return mixed `null` if the value has been claimed via
     * {@see ISyncContext::claimFilter()} or wasn't passed to the operation.
     *
     * @see ISyncContext::withArgs()
     */
    public function getFilter(string $key, bool $orValue = true);

    /**
     * Get the value of a filter passed to the context via optional sync
     * operation arguments
     *
     * Unlike other {@see ISyncContext} methods,
     * {@see ISyncContext::claimFilter()} modifies the object it is called on
     * instead of returning a modified clone.
     *
     * If `$orValue` is `true` and a value for `$key` has been applied to the
     * context via {@see IProviderContext::withValue()}, it is returned if there
     * is no matching filter.
     *
     * @return mixed `null` if the value has already been claimed or wasn't
     * passed to the operation.
     *
     * @see ISyncContext::withArgs()
     */
    public function claimFilter(string $key, bool $orValue = true);

    /**
     * Get the "work offline" status applied via online(), offline() or
     * offlineFirst()
     *
     * If `null` (the default), entities are returned from the local entity
     * store if they are sufficiently fresh or if the provider cannot be
     * reached.
     *
     * If `true`, the local entity store is used unconditionally.
     *
     * If `false`, the local entity store is unconditionally ignored.
     */
    public function getOffline(): ?bool;

    /**
     * Get the deferred sync entity policy applied to the context
     *
     * @return DeferralPolicy::*
     */
    public function getDeferralPolicy();

    /**
     * Get the hydration policy applied to the context for a given sync entity
     *
     * @param class-string<ISyncEntity>|null $entity
     * @return HydrationPolicy::*
     */
    public function getHydrationPolicy(?string $entity): int;
}
