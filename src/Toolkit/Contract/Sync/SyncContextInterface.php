<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Core\Providable;
use Salient\Contract\Core\ProviderContextInterface;
use Salient\Sync\Exception\SyncEntityRecursionException;
use Salient\Sync\Exception\SyncInvalidFilterException;
use Salient\Sync\AbstractSyncProvider;
use DateTimeInterface;

/**
 * The context within which sync entities are instantiated by a provider
 *
 * @extends ProviderContextInterface<SyncProviderInterface,SyncEntityInterface>
 */
interface SyncContextInterface extends ProviderContextInterface
{
    /**
     * Normalise optional sync operation arguments and apply them to the context
     *
     * If, after removing the operation's mandatory arguments from `$args`, the
     * remaining values match one of the following signatures, they are mapped
     * to a filter surfaced by {@see SyncContextInterface::getFilters()},
     * {@see SyncContextInterface::getFilter()} and
     * {@see SyncContextInterface::claimFilter()}:
     *
     * 1. One array argument (`fn(...$mandatoryArgs, array $filter)`)
     *
     *    - Keys containing only letters, numbers, hyphens and underscores are
     *      converted to snake_case
     *    - Keys containing other characters, e.g. `'$orderby'`, are left as-is
     *    - {@see SyncEntityInterface} instances are replaced with their
     *      respective IDs
     *    - Empty and numeric keys (e.g. `''` or `'42'` after snake_case
     *      conversion) are invalid
     *
     * 2. A list of identifiers (`fn(...$mandatoryArgs, int|string ...$ids)`)
     *
     *    - Converted to `[ 'id' => $ids ]`
     *
     * 3. A list of entities (`fn(...$mandatoryArgs, SyncEntityInterface
     *    ...$entities)`)
     *
     *    - Entities are grouped by snake_case {@see Providable::getService()}
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
     * Using {@see SyncContextInterface::claimFilter()} to claim filters is
     * recommended. Depending on the provider's {@see FilterPolicy}, unclaimed
     * filters may cause requests to fail.
     *
     * When a filter is claimed, it is removed from the context.
     * {@see SyncContextInterface::getFilters()} and
     * {@see SyncContextInterface::getFilter()} only return unclaimed filters.
     *
     * @param SyncOperation::* $operation
     * @param mixed ...$args Sync operation arguments, NOT including the
     * {@see SyncContextInterface} argument.
     * @return static
     */
    public function withArgs($operation, ...$args);

    /**
     * Use a callback to enforce the provider's unclaimed filter policy
     *
     * Allows providers to enforce their {@see FilterPolicy} by calling
     * {@see SyncContextInterface::applyFilterPolicy()} in scenarios where
     * enforcement before a sync operation starts isn't possible.
     *
     * @see SyncContextInterface::applyFilterPolicy()
     *
     * @param (callable(SyncContextInterface, ?bool &$returnEmpty, array{}|null &$empty): void)|null $callback
     * @return static
     */
    public function withFilterPolicyCallback(?callable $callback);

    /**
     * Reject entities from the local entity store
     *
     * @return static
     */
    public function online();

    /**
     * Reject entities from the provider
     *
     * An exception is thrown if the local entity store is unable to satisfy
     * subsequent entity requests.
     *
     * @return static
     */
    public function offline();

    /**
     * Accept entities from the local entity store if they are sufficiently
     * fresh or if the provider cannot be reached
     *
     * This is the default behaviour.
     *
     * @return static
     */
    public function offlineFirst();

    /**
     * Apply the given deferral policy to the context
     *
     * @param DeferralPolicy::* $policy
     * @return static
     */
    public function withDeferralPolicy($policy);

    /**
     * Apply the given hydration policy to the context
     *
     * @param HydrationPolicy::* $policy
     * @param class-string<SyncEntityInterface>|null $entity Limit the scope of
     * the change to an entity and its subclasses.
     * @param array<int<1,max>>|int<1,max>|null $depth Limit the scope of the
     * change to entities at a given `$depth` from the current context.
     * @return static
     */
    public function withHydrationPolicy(
        int $policy,
        ?string $entity = null,
        $depth = null
    );

    /**
     * Push the entity propagating the context onto the stack after checking if
     * it is already present
     *
     * {@see SyncContextInterface::maybeThrowRecursionException()} fails with an
     * exception if `$entity` is already in the stack.
     *
     * @return static
     */
    public function pushWithRecursionCheck(SyncEntityInterface $entity);

    /**
     * Throw an exception if recursion is detected
     *
     * @throws SyncEntityRecursionException if
     * {@see SyncContextInterface::pushWithRecursionCheck()} detected recursion.
     */
    public function maybeThrowRecursionException(): void;

    /**
     * Run the unclaimed filter policy callback
     *
     * {@see AbstractSyncProvider::run()} calls this method on your behalf and
     * is recommended for providers where sync operations are performed by
     * declared methods.
     *
     * Example:
     *
     * ```php
     * <?php
     * class Provider extends HttpSyncProvider
     * {
     *     public function getEntities(SyncContextInterface $ctx): iterable
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
    public function applyFilterPolicy(?bool &$returnEmpty, ?array &$empty): void;

    /**
     * Get the filters passed to the context via optional sync operation
     * arguments
     *
     * @see SyncContextInterface::withArgs()
     *
     * @return array<string,(int|string|DateTimeInterface|float|bool|null)[]|int|string|DateTimeInterface|float|bool|null>
     */
    public function getFilters(): array;

    /**
     * Get the value of a filter passed to the context via optional sync
     * operation arguments
     *
     * If `$orValue` is `true` and a value for `$key` has been applied to the
     * context via {@see ProviderContextInterface::withValue()}, it is returned
     * if there is no matching filter.
     *
     * @return (int|string|DateTimeInterface|float|bool|null)[]|int|string|DateTimeInterface|float|bool|null
     * `null` if the value has been claimed via
     * {@see SyncContextInterface::claimFilter()} or wasn't passed to the
     * operation.
     *
     * @see SyncContextInterface::withArgs()
     */
    public function getFilter(string $key, bool $orValue = true);

    /**
     * Get the value of a filter passed to the context via optional sync
     * operation arguments
     *
     * Unlike other {@see SyncContextInterface} methods,
     * {@see SyncContextInterface::claimFilter()} modifies the object it is
     * called on instead of returning a modified instance.
     *
     * If `$orValue` is `true` and a value for `$key` has been applied to the
     * context via {@see ProviderContextInterface::withValue()}, it is returned
     * if there is no matching filter.
     *
     * @see SyncContextInterface::withArgs()
     *
     * @return (int|string|DateTimeInterface|float|bool|null)[]|int|string|DateTimeInterface|float|bool|null
     * `null` if the value has already been claimed or wasn't passed to the
     * operation.
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
     * @param class-string<SyncEntityInterface>|null $entity
     * @return HydrationPolicy::*
     */
    public function getHydrationPolicy(?string $entity): int;
}
