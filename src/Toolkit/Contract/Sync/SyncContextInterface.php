<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Core\ProviderContextInterface;
use Salient\Contract\Sync\Exception\InvalidFilterSignatureExceptionInterface;
use DateTimeInterface;

/**
 * The context within which sync entities are instantiated by a provider
 *
 * @extends ProviderContextInterface<SyncProviderInterface,SyncEntityInterface>
 */
interface SyncContextInterface extends ProviderContextInterface
{
    /**
     * @param bool $detectRecursion If `true`, check if the context has already
     * been propagated for `$entity` and return the result via
     * {@see SyncContextInterface::recursionDetected()}.
     */
    public function pushEntity($entity, bool $detectRecursion = false);

    /**
     * Check if recursion was detected during the last call to pushEntity()
     *
     * @phpstan-assert-if-true !null $this->getLastEntity()
     */
    public function recursionDetected(): bool;

    /**
     * Check if the context has an unclaimed filter applied via non-mandatory
     * sync operation arguments
     *
     * @param string|null $key If `null`, check if the context has any unclaimed
     * filters.
     */
    public function hasFilter(?string $key = null): bool;

    /**
     * Get the value of an unclaimed filter applied to the context via
     * non-mandatory sync operation arguments
     *
     * If `$orValue` is `true` and the context has a value for `$key`, it is
     * returned if there is no matching filter, otherwise `null` is returned.
     *
     * @return (int|string|float|bool|null)[]|int|string|float|bool|null
     */
    public function getFilter(string $key, bool $orValue = true);

    /**
     * Claim the value of an unclaimed filter applied via non-mandatory sync
     * operation arguments, removing it from the context
     *
     * This method deliberately breaks the context's immutability contract.
     *
     * If `$orValue` is `true` and the context has a value for `$key`, it is
     * returned if there is no matching filter, otherwise `null` is returned.
     *
     * @return (int|string|float|bool|null)[]|int|string|float|bool|null
     */
    public function claimFilter(string $key, bool $orValue = true);

    /**
     * Get unclaimed filters applied to the context via non-mandatory sync
     * operation arguments
     *
     * @return array<string,(int|string|float|bool|null)[]|int|string|float|bool|null>
     */
    public function getFilters(): array;

    /**
     * Get an instance with filters derived from non-mandatory sync operation
     * arguments
     *
     * An exception is thrown if non-mandatory arguments in `$args` don't match
     * one of the following signatures.
     *
     * 1. An associative array (`fn(..., array<string,mixed> $filters)`)
     *
     *    - Keys are trimmed
     *    - Keys that contain letters or numbers, optionally with inner
     *      whitespace, underscores or hyphens, are converted to snake_case
     *
     * 2. A list of identifiers (`fn(..., int ...$ids)` or `fn(..., string ...$ids)`)
     *
     *    - Becomes `[ 'id' => $ids ]`
     *
     * 3. A list of entities (`fn(..., SyncEntityInterface ...$entities)`)
     *
     *    - Grouped by snake_case {@see ServiceAwareInterface::getService()}
     *      short names
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
     *   have an identifier ({@see SyncEntityInterface::getId()} returns `null`)
     *   or do not have the same provider as the context
     * - {@see DateTimeInterface} instances are formatted by the provider's date
     *   formatter.
     * - The result is surfaced via {@see SyncContextInterface::hasFilter()},
     *   {@see SyncContextInterface::getFilter()},
     *   {@see SyncContextInterface::claimFilter()} and
     *   {@see SyncContextInterface::getFilters()}.
     *
     * {@see SyncContextInterface::claimFilter()} should generally be used to
     * prevent sync operation failures caused by unclaimed filters.
     *
     * @param SyncOperation::* $operation
     * @param mixed ...$args Sync operation arguments, not including the
     * {@see SyncContextInterface} argument.
     * @return static
     * @throws InvalidFilterSignatureExceptionInterface
     */
    public function withArgs(int $operation, ...$args);

    /**
     * Run the unclaimed filter policy callback
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
     * Get the deferral policy applied to the context
     *
     * @return DeferralPolicy::*
     */
    public function getDeferralPolicy(): int;

    /**
     * Get an instance with the given deferral policy
     *
     * @param DeferralPolicy::* $policy
     * @return static
     */
    public function withDeferralPolicy(int $policy);

    /**
     * Get the hydration policy applied to the context, optionally scoped by
     * sync entity type
     *
     * @param class-string<SyncEntityInterface>|null $entityType
     * @return HydrationPolicy::*
     */
    public function getHydrationPolicy(?string $entityType): int;

    /**
     * Get an instance with the given hydration policy, optionally scoped by
     * sync entity type and/or depth
     *
     * @param HydrationPolicy::* $policy
     * @param class-string<SyncEntityInterface>|null $entityType Limit the scope
     * of the change to an entity type.
     * @param array<int<1,max>>|int<1,max>|null $depth Limit the scope of the
     * change to entities at a given `$depth` from the current context.
     * @return static
     */
    public function withHydrationPolicy(
        int $policy,
        ?string $entityType = null,
        $depth = null
    );

    /**
     * Get the offline mode applied to the context
     *
     * @return bool|null - `null` (default): entities are returned from the
     * local entity store if possible, otherwise they are retrieved from the
     * provider.
     * - `true`: entities are returned from the local entity store without
     *   falling back to retrieval from the provider.
     * - `false`: entities are retrieved from the provider without consulting
     *   the local entity store.
     */
    public function getOffline(): ?bool;

    /**
     * Get an instance with the given offline mode
     *
     * @param bool|null $offline - `null` (default): return entities from the
     * local entity store if possible, otherwise retrieve them from the
     * provider.
     * - `true`: return entities from the local entity store without falling
     *   back to retrieval from the provider.
     * - `false`: retrieve entities from the provider without consulting the
     *   local entity store.
     * @return static
     */
    public function withOffline(?bool $offline);
}
