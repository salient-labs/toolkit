<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Core\ProviderContextInterface;
use Salient\Sync\Exception\SyncEntityRecursionException;
use Salient\Sync\Exception\SyncInvalidFilterException;
use Salient\Sync\Exception\SyncInvalidFilterSignatureException;
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
     * Normalise non-mandatory arguments to a sync operation and apply them to
     * the context
     *
     * An exception is thrown if `$args` doesn't match one of the following
     * non-mandatory argument signatures.
     *
     * 1. An associative array (`fn(..., array<string,mixed> $filter)`)
     *
     *    - Keys are trimmed
     *    - Keys that only contain space-, hyphen- or underscore-delimited
     *      letters and numbers are converted to snake_case
     *    - Empty and numeric keys are invalid
     *
     * 2. A list of identifiers (`fn(..., [ int ...$ids | string ...$ids ] )`)
     *
     *    - Becomes `[ 'id' => $ids ]`
     *
     * 3. A list of entities (`fn(..., SyncEntityInterface ...$entities)`)
     *
     *    - Grouped by {@see ServiceAwareInterface::getService() service} after
     *      removing namespace and converting to snake_case
     *    - Example: `[ 'faculty' => [42, 71], 'faculty_user' => [101] ]`
     *
     * 4. No arguments (`fn(...)`)
     *
     *    - Becomes `[]`
     *
     * In all cases:
     *
     * - {@see SyncEntityInterface} objects are replaced with their identifiers
     * - An exception is thrown if any {@see SyncEntityInterface} objects do not
     *   have an identifier ({@see SyncEntityInterface::id()} returns `null`) or
     *   do not have the same provider as the context
     * - {@see DateTimeInterface} instances are converted to ISO-8601 strings
     * - The result is surfaced via {@see SyncContextInterface::getFilter()},
     *   {@see SyncContextInterface::claimFilter()} and their variants.
     *
     * Using {@see SyncContextInterface::claimFilter()} to "claim" filters is
     * recommended. Depending on the provider's {@see FilterPolicy}, unclaimed
     * filters may cause requests to fail.
     *
     * When a filter is "claimed", it is removed from the context.
     *
     * @param SyncOperation::* $operation
     * @param mixed ...$args Sync operation arguments, not including the
     * {@see SyncContextInterface} argument.
     * @return static
     * @throws SyncInvalidFilterSignatureException
     */
    public function withFilter($operation, ...$args);

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
     * Only retrieve entities from the provider
     *
     * @return static
     */
    public function online();

    /**
     * Only retrieve entities from the local entity store
     *
     * @return static
     */
    public function offline();

    /**
     * Retrieve entities from the provider as needed to update the local entity
     * store
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
     * Get the value of a filter passed to the context via optional sync
     * operation arguments
     *
     * If `$key` is `null`, all filters are returned.
     *
     * If `$orValue` is `true` and a value for `$key` has been applied via
     * {@see ProviderContextInterface::withValue()}, it is returned if there is
     * no matching filter.
     *
     * Otherwise, `null` is returned if `$key` has been claimed via
     * {@see SyncContextInterface::claimFilter()} or wasn't passed to the
     * operation.
     *
     * @see SyncContextInterface::withFilter()
     *
     * @template TKey of string|null
     *
     * @param TKey $key
     * @return (TKey is string ?
     *     (int|string|float|bool|null)[]|int|string|float|bool|null :
     *     array<string,(int|string|float|bool|null)[]|int|string|float|bool|null>
     * )
     */
    public function getFilter(?string $key = null, bool $orValue = true);

    /**
     * Get the value of an integer passed to the context via optional sync
     * operation arguments
     *
     * Same as {@see SyncContextInterface::getFilter()}, but an exception is
     * thrown if the value is not an integer.
     *
     * @throws SyncInvalidFilterException
     */
    public function getFilterInt(string $key, bool $orValue = true): ?int;

    /**
     * Get the value of a string passed to the context via optional sync
     * operation arguments
     *
     * Same as {@see SyncContextInterface::getFilter()}, but an exception is
     * thrown if the value is not a string.
     *
     * @throws SyncInvalidFilterException
     */
    public function getFilterString(string $key, bool $orValue = true): ?string;

    /**
     * Get the value of an integer or string passed to the context via optional
     * sync operation arguments
     *
     * Same as {@see SyncContextInterface::getFilter()}, but an exception is
     * thrown if the value is not an integer or string.
     *
     * @return int|string|null
     * @throws SyncInvalidFilterException
     */
    public function getFilterArrayKey(string $key, bool $orValue = true);

    /**
     * Get a list of integers passed to the context via optional sync operation
     * arguments
     *
     * Same as {@see SyncContextInterface::getFilter()}, but an exception is
     * thrown if the value is not a list of integers.
     *
     * @return int[]|null
     * @throws SyncInvalidFilterException
     */
    public function getFilterIntList(string $key, bool $orValue = true): ?array;

    /**
     * Get a list of strings passed to the context via optional sync operation
     * arguments
     *
     * Same as {@see SyncContextInterface::getFilter()}, but an exception is
     * thrown if the value is not a list of strings.
     *
     * @return string[]|null
     * @throws SyncInvalidFilterException
     */
    public function getFilterStringList(string $key, bool $orValue = true): ?array;

    /**
     * Get a list of integers and strings passed to the context via optional
     * sync operation arguments
     *
     * Same as {@see SyncContextInterface::getFilter()}, but an exception is
     * thrown if the value is not a list of integers and strings.
     *
     * @return (int|string)[]|null
     * @throws SyncInvalidFilterException
     */
    public function getFilterArrayKeyList(string $key, bool $orValue = true): ?array;

    /**
     * Get the value of a filter passed to the context via optional sync
     * operation arguments
     *
     * Unlike other {@see SyncContextInterface} methods,
     * {@see SyncContextInterface::claimFilter()} modifies the object it is
     * called on instead of returning a modified instance.
     *
     * If `$orValue` is `true` and a value for `$key` has been applied via
     * {@see ProviderContextInterface::withValue()}, it is returned if there is
     * no matching filter.
     *
     * Otherwise, `null` is returned if `$key` has been claimed via
     * {@see SyncContextInterface::claimFilter()} or wasn't passed to the
     * operation.
     *
     * @see SyncContextInterface::withFilter()
     *
     * @return (int|string|float|bool|null)[]|int|string|float|bool|null
     */
    public function claimFilter(string $key, bool $orValue = true);

    /**
     * Get the value of an integer passed to the context via optional sync
     * operation arguments
     *
     * Same as {@see SyncContextInterface::claimFilter()}, but an exception is
     * thrown if the value is not an integer.
     *
     * @throws SyncInvalidFilterException
     */
    public function claimFilterInt(string $key, bool $orValue = true): ?int;

    /**
     * Get the value of a string passed to the context via optional sync
     * operation arguments
     *
     * Same as {@see SyncContextInterface::claimFilter()}, but an exception is
     * thrown if the value is not a string.
     *
     * @throws SyncInvalidFilterException
     */
    public function claimFilterString(string $key, bool $orValue = true): ?string;

    /**
     * Get the value of an integer or string passed to the context via optional
     * sync operation arguments
     *
     * Same as {@see SyncContextInterface::claimFilter()}, but an exception is
     * thrown if the value is not an integer or string.
     *
     * @return int|string|null
     * @throws SyncInvalidFilterException
     */
    public function claimFilterArrayKey(string $key, bool $orValue = true);

    /**
     * Get a list of integers passed to the context via optional sync operation
     * arguments
     *
     * Same as {@see SyncContextInterface::claimFilter()}, but an exception is
     * thrown if the value is not a list of integers.
     *
     * @return int[]|null
     * @throws SyncInvalidFilterException
     */
    public function claimFilterIntList(string $key, bool $orValue = true): ?array;

    /**
     * Get a list of strings passed to the context via optional sync operation
     * arguments
     *
     * Same as {@see SyncContextInterface::claimFilter()}, but an exception is
     * thrown if the value is not a list of strings.
     *
     * @return string[]|null
     * @throws SyncInvalidFilterException
     */
    public function claimFilterStringList(string $key, bool $orValue = true): ?array;

    /**
     * Get a list of integers and strings passed to the context via optional
     * sync operation arguments
     *
     * Same as {@see SyncContextInterface::claimFilter()}, but an exception is
     * thrown if the value is not a list of integers and strings.
     *
     * @return (int|string)[]|null
     * @throws SyncInvalidFilterException
     */
    public function claimFilterArrayKeyList(string $key, bool $orValue = true): ?array;

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
