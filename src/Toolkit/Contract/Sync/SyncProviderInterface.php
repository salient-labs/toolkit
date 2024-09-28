<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Core\ProviderInterface;
use Closure;

/**
 * Base interface for providers that sync entities to and from third-party
 * backends
 *
 * @extends ProviderInterface<SyncContextInterface>
 */
interface SyncProviderInterface extends ProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getContext(): SyncContextInterface;

    /**
     * Get the provider ID assigned to the backend instance by its entity store
     */
    public function getProviderId(): int;

    /**
     * Get the provider's implementation of sync operations for an entity
     *
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $entity
     * @return SyncDefinitionInterface<T,$this>
     */
    public function getDefinition(string $entity): SyncDefinitionInterface;

    /**
     * Get the provider's default unclaimed filter policy
     *
     * @return FilterPolicy::*|null
     */
    public function getFilterPolicy(): ?int;

    /**
     * True if a value is of the correct type and format to be an entity ID
     *
     * @param int|string $id
     * @param class-string<SyncEntityInterface> $entity
     */
    public function isValidIdentifier($id, string $entity): bool;

    /**
     * Get the provider's entity store
     */
    public function getStore(): SyncStoreInterface;

    /**
     * Use an entity-agnostic interface to the provider's implementation of sync
     * operations for an entity
     *
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $entity
     * @return SyncEntityProviderInterface<T>
     */
    public function with(string $entity, ?SyncContextInterface $context = null): SyncEntityProviderInterface;

    /**
     * Perform a sync operation after validating its context
     *
     * @template T
     * @template TOutput of iterable<T>|T
     *
     * @param Closure(): TOutput $operation
     * @return TOutput
     */
    public function runOperation(SyncContextInterface $context, Closure $operation);

    /**
     * Filter the output of a sync operation if required by its context
     *
     * @template T of SyncEntityInterface
     * @template TOutput of iterable<T>|T
     *
     * @param TOutput $output
     * @return TOutput
     */
    public function filterOperationOutput(SyncContextInterface $context, $output);
}
