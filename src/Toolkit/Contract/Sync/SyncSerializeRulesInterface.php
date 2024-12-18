<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Core\SerializeRulesInterface;
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
     * Check if values are being serialized for an entity store
     */
    public function getForSyncStore(): bool;

    /**
     * Get an instance that serializes values for an entity store
     *
     * @return static
     */
    public function withForSyncStore(?bool $forSyncStore = true);

    /**
     * Check if canonical identifiers of sync entities are serialized
     */
    public function getIncludeCanonicalId(): bool;

    /**
     * Get an instance that serializes canonical identifiers of sync entities
     *
     * @return static
     */
    public function withIncludeCanonicalId(?bool $include = true);
}
