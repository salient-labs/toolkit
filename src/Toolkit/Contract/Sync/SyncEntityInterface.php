<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Identifiable;
use Salient\Contract\Core\Nameable;
use Salient\Contract\Core\Providable;
use Salient\Contract\Core\ProvidableEntityInterface;
use Salient\Contract\Core\Relatable;
use Salient\Contract\Sync\SyncEntityLinkType as LinkType;
use Salient\Sync\Exception\SyncEntityNotFoundException;
use Salient\Sync\AbstractSyncEntity;
use Salient\Sync\SyncSerializeRules as SerializeRules;
use JsonSerializable;

/**
 * Represents the state of an entity in an external system
 *
 * @see AbstractSyncEntity
 *
 * @extends ProvidableEntityInterface<SyncProviderInterface,SyncContextInterface>
 */
interface SyncEntityInterface extends
    Identifiable,
    Nameable,
    ProvidableEntityInterface,
    Relatable,
    JsonSerializable
{
    /**
     * Get the name of the entity
     */
    public function name(): string;

    /**
     * Get an instance of the entity's default provider
     */
    public static function defaultProvider(?ContainerInterface $container = null): SyncProviderInterface;

    /**
     * Get an interface to perform sync operations on the entity with its
     * default provider
     *
     * @return SyncEntityProviderInterface<static>
     */
    public static function withDefaultProvider(?ContainerInterface $container = null, ?SyncContextInterface $context = null): SyncEntityProviderInterface;

    /**
     * Get the entity's default serialization rules
     *
     * @return SerializeRules<static>
     */
    public static function getSerializeRules(?ContainerInterface $container = null): SerializeRules;

    /**
     * Get the plural form of the entity's class name
     *
     * e.g. `Faculty::plural()` should return `'Faculties'`.
     */
    public static function plural(): string;

    /**
     * Resolve a name or entity ID to the entity ID of one matching entity
     *
     * Returns:
     *
     * - `null` if `$nameOrId` is `null`
     * - `$nameOrId` if it is a valid identifier for the entity in the given
     *   provider (see {@see SyncProviderInterface::isValidIdentifier()}), or
     * - the entity ID of the entity to which `$nameOrId` resolves
     *
     * A {@see SyncEntityNotFoundException} is thrown if:
     *
     * - there are no matching entities, or
     * - there are multiple matching entities
     *
     * @param int|string|null $nameOrId
     * @param SyncProviderInterface|SyncContextInterface $providerOrContext
     * @return int|string|null
     */
    public static function idFromNameOrId(
        $nameOrId,
        $providerOrContext,
        ?float $uncertaintyThreshold = null,
        ?string $nameProperty = null,
        ?float &$uncertainty = null
    );

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
     * underlying {@see Providable::getService()}, it is regarded as the
     * entity's canonical backend.
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
     * {@see SyncEntityInterface} encountered during this recursive operation.
     *
     * @return array<string,mixed>
     *
     * @see SyncEntityInterface::getSerializeRules()
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
    public function toLink(?ContainerInterface $container = null, int $type = LinkType::DEFAULT, bool $compact = true): array;

    /**
     * Get the entity's canonical location in the form of a URI
     *
     * Inspired by OData.
     */
    public function uri(?ContainerInterface $container = null, bool $compact = true): string;
}
