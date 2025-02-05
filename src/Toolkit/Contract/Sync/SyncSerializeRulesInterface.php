<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Core\Entity\SerializeRulesInterface;
use Closure;

/**
 * @api
 *
 * @template TEntity of SyncEntityInterface
 *
 * @extends SerializeRulesInterface<TEntity>
 */
interface SyncSerializeRulesInterface extends SerializeRulesInterface
{
    /**
     * @inheritDoc
     *
     * @return array<string,array{string|null,(Closure(mixed $value, SyncStoreInterface|null $store=): mixed)|null}>
     */
    public function getReplaceableKeys(?string $class, ?string $baseClass, array $path): array;

    /**
     * Check if entities should be serialized for an entity store
     */
    public function getForSyncStore(): bool;

    /**
     * Get an instance where entities are serialized for an entity store
     *
     * @return static
     */
    public function withForSyncStore(?bool $forSyncStore = true): self;

    /**
     * Check if the canonical identifiers of sync entities should be included
     * when they are serialized
     */
    public function getCanonicalId(): bool;

    /**
     * Get an instance where the canonical identifiers of sync entities are
     * included when they are serialized
     *
     * @return static
     */
    public function withCanonicalId(?bool $include = true): self;
}
