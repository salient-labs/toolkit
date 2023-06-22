<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use JsonSerializable;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProviderEntity;
use Lkrms\Contract\ReturnsDescription;
use Lkrms\Sync\Catalog\SyncSerializeLinkType;
use Lkrms\Sync\Support\SyncSerializeRules;

/**
 * Represents the state of an entity in an external system
 *
 * @extends IProviderEntity<ISyncProvider,ISyncContext>
 * @see \Lkrms\Sync\Concept\SyncEntity
 */
interface ISyncEntity extends IProviderEntity, ReturnsDescription, JsonSerializable
{
    /**
     * Get an instance of the entity's current provider
     *
     */
    public static function defaultProvider(?IContainer $container = null): ISyncProvider;

    /**
     * Get an interface to perform sync operations on the entity with its
     * current provider
     *
     * @return ISyncEntityProvider<static>
     */
    public static function withDefaultProvider(?IContainer $container = null): ISyncEntityProvider;

    /**
     * Get the entity's default serialization rules
     *
     * @return SyncSerializeRules<static>
     */
    public static function getSerializeRules(?IContainer $container = null): SyncSerializeRules;

    /**
     * Called when the class is registered with an entity store
     *
     * @internal
     * @see \Lkrms\Sync\Support\SyncStore::entityType()
     */
    public static function setEntityTypeId(int $entityTypeId): void;

    /**
     * @internal
     */
    public static function entityTypeId(): ?int;

    /**
     * The unique identifier assigned to the entity by its provider
     *
     * @return int|string|null
     */
    public function id();

    /**
     * The unique identifier assigned to the entity by its canonical backend
     *
     * An {@see ISyncEntity}'s canonical backend is the provider regarded as the
     * "single source of truth" for its underlying
     * {@see \Lkrms\Contract\IProvidable::service()} and any properties that
     * aren't "owned" by another provider.
     *
     * To improve the accuracy and performance of sync operations, providers
     * should propagate this value to and from backends capable of storing it,
     * but this is not strictly required.
     *
     * @return int|string|null
     */
    public function canonicalId();

    /**
     * Serialize the entity and any nested entities
     *
     * The entity's {@see SyncSerializeRules} are applied to each
     * {@see ISyncEntity} encountered during this recursive operation.
     *
     * @return array<string,mixed>
     * @see ISyncEntity::getSerializeRules()
     */
    public function toArray(): array;

    /**
     * Use custom rules to serialize the entity and any nested entities
     *
     * @param SyncSerializeRules<static> $rules
     * @return array<string,mixed>
     */
    public function toArrayWith(SyncSerializeRules $rules): array;

    /**
     * Get the entity's canonical location in the form of an array
     *
     * Inspired by JSON-LD.
     *
     * @param SyncSerializeLinkType::* $type
     * @return array<string,int|string>
     * @see SyncSerializeLinkType
     */
    public function toLink(int $type = SyncSerializeLinkType::DEFAULT, bool $compact = true): array;

    /**
     * Get the entity's canonical location in the form of a URI
     *
     * Inspired by OData.
     *
     */
    public function uri(bool $compact = true): string;
}
