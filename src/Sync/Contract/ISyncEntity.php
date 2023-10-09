<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IProviderEntity;
use Lkrms\Contract\IRelatable;
use Lkrms\Contract\ReturnsDescription;
use Lkrms\Contract\ReturnsIdentifier;
use Lkrms\Sync\Catalog\SyncEntityLinkType as LinkType;
use Lkrms\Sync\Support\SyncSerializeRules as SerializeRules;
use Lkrms\Sync\Support\SyncStore;
use JsonSerializable;

/**
 * Represents the state of an entity in an external system
 *
 * @extends IProviderEntity<ISyncProvider,ISyncContext>
 *
 * @see \Lkrms\Sync\Concept\SyncEntity
 *
 * @extends IProviderEntity<ISyncProvider,ISyncContext>
 */
interface ISyncEntity extends
    ReturnsIdentifier,
    ReturnsDescription,
    IProviderEntity,
    IRelatable,
    JsonSerializable
{
    /**
     * Get an instance of the entity's default provider
     */
    public static function defaultProvider(?IContainer $container = null): ISyncProvider;

    /**
     * Get an interface to perform sync operations on the entity with its
     * default provider
     *
     * @return ISyncEntityProvider<static>
     */
    public static function withDefaultProvider(?IContainer $container = null, ?ISyncContext $context = null): ISyncEntityProvider;

    /**
     * Get the entity's default serialization rules
     *
     * @return SerializeRules<static>
     */
    public static function getSerializeRules(?IContainer $container = null): SerializeRules;

    /**
     * Called when the entity is registered with an entity store
     *
     * @see SyncStore::entityType()
     */
    public static function setEntityTypeId(int $entityTypeId): void;

    /**
     * Get the entity type ID assigned to the entity by the entity store
     */
    public static function getEntityTypeId(): ?int;

    /**
     * The unique identifier assigned to the entity by its provider
     *
     * @return int|string|null
     */
    public function id();

    /**
     * The unique identifier assigned to the entity by its canonical backend
     *
     * If a provider is bound to the service container as the default
     * implementation of the provider interface associated with an entity's
     * underlying {@see IProvidable::service()}, it is regarded as the entity's
     * canonical backend.
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
     * The entity's {@see SerializeRules} are applied to each
     * {@see ISyncEntity} encountered during this recursive operation.
     *
     * @return array<string,mixed>
     * @see ISyncEntity::getSerializeRules()
     */
    public function toArray(): array;

    /**
     * Use custom rules to serialize the entity and any nested entities
     *
     * @param SerializeRules<static> $rules
     * @return array<string,mixed>
     */
    public function toArrayWith(SerializeRules $rules): array;

    /**
     * Get the entity's canonical location in the form of an array
     *
     * Inspired by JSON-LD.
     *
     * @param LinkType::* $type
     * @return array<string,int|string>
     */
    public function toLink(int $type = LinkType::DEFAULT, bool $compact = true): array;

    /**
     * Get the entity's canonical location in the form of a URI
     *
     * Inspired by OData.
     */
    public function uri(bool $compact = true): string;
}
