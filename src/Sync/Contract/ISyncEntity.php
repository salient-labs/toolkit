<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use JsonSerializable;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProviderEntity;
use Lkrms\Contract\ReturnsDescription;
use Lkrms\Sync\Support\SyncSerializeLinkType;
use Lkrms\Sync\Support\SyncSerializeRules;
use Lkrms\Sync\Support\SyncSerializeRulesBuilder;

/**
 * Represents the state of an entity in an external system
 *
 * @see \Lkrms\Sync\Concept\SyncEntity
 */
interface ISyncEntity extends IProviderEntity, ReturnsDescription, JsonSerializable
{
    /**
     * Get an interface to the entity's current provider
     *
     * @return ISyncEntityProvider<static>
     */
    public static function backend(?IContainer $container = null): ISyncEntityProvider;

    /**
     * Get a SyncSerializeRules builder for the entity, optionally inheriting
     * its default rules
     *
     */
    public static function buildSerializeRules(?IContainer $container = null, bool $inherit = true): SyncSerializeRulesBuilder;

    /**
     * Get the entity's default serialization rules
     *
     */
    public static function serializeRules(?IContainer $container = null): SyncSerializeRules;

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
     * Serialize the entity and any nested entities
     *
     * The entity's {@see SyncSerializeRules} are applied to each
     * {@see ISyncEntity} encountered during this recursive operation.
     *
     * @see ISyncEntity::serializeRules()
     */
    public function toArray(): array;

    /**
     * Use custom rules to serialize the entity and any nested entities
     *
     * @param SyncSerializeRulesBuilder|SyncSerializeRules $rules
     */
    public function toArrayWith($rules): array;

    /**
     * Get the entity's canonical location in the form of an array
     *
     * Inspired by JSON-LD.
     *
     * @psalm-param SyncSerializeLinkType::* $type
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
