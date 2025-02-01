<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Entity\ProviderEntityInterface;
use Salient\Contract\Core\Entity\Relatable;
use Salient\Contract\Core\Entity\Temporal;
use Salient\Contract\Core\HasId;
use Salient\Contract\Core\HasName;
use Salient\Contract\Sync\Exception\SyncEntityNotFoundExceptionInterface;
use JsonSerializable;

/**
 * Represents the state of an entity in an external system
 *
 * @extends ProviderEntityInterface<SyncProviderInterface,SyncContextInterface>
 */
interface SyncEntityInterface extends
    HasId,
    HasName,
    ProviderEntityInterface,
    Relatable,
    Temporal,
    JsonSerializable
{
    /**
     * Get the entity's default provider
     */
    public static function getDefaultProvider(ContainerInterface $container): SyncProviderInterface;

    /**
     * Perform sync operations on the entity using its default provider
     *
     * @return SyncEntityProviderInterface<static>
     */
    public static function withDefaultProvider(ContainerInterface $container, ?SyncContextInterface $context = null): SyncEntityProviderInterface;

    /**
     * Get the entity's serialization rules
     *
     * @return SyncSerializeRulesInterface<static>
     */
    public static function getSerializeRules(): SyncSerializeRulesInterface;

    /**
     * Get the plural form of the entity's short name
     *
     * If this method returns a value other than `null` or the unqualified name
     * of the entity, it may be used to identify provider methods that implement
     * sync operations on the entity.
     *
     * For example, if `Faculty::getPlural()` returns `null`, a provider may
     * implement `Faculty` sync operations via one or more of the following:
     *
     * - `create_faculty()`
     * - `get_faculty()`
     * - `update_faculty()`
     * - `delete_faculty()`
     * - `createList_faculty()`
     * - `getList_faculty()`
     * - `updateList_faculty()`
     * - `deleteList_faculty()`
     *
     * If the same method returns `"Faculties"`, these are also recognised as
     * `Faculty` sync implementations:
     *
     * - `createFaculty()`
     * - `getFaculty()`
     * - `updateFaculty()`
     * - `deleteFaculty()`
     * - `createFaculties()`
     * - `getFaculties()`
     * - `updateFaculties()`
     * - `deleteFaculties()`
     */
    public static function getPlural(): ?string;

    /**
     * Get the unique identifier assigned to the entity by its provider
     */
    public function getId();

    /**
     * Get the unique identifier assigned to the entity by its canonical backend
     *
     * If a provider is bound to the service container as the default
     * implementation of the entity's underlying provider interface, it is
     * regarded as its canonical backend.
     *
     * To improve the accuracy and performance of sync operations, providers
     * should propagate this value to and from backends capable of storing it.
     *
     * @return int|string|null
     */
    public function getCanonicalId();

    /**
     * Get the name of the entity
     */
    public function getName(): string;

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
     * A {@see SyncEntityNotFoundExceptionInterface} is thrown if:
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
     * Serialize the entity and any nested entities
     *
     * Rules returned by {@see SyncEntityInterface::getSerializeRules()} are
     * used.
     *
     * @return array<string,mixed>
     */
    public function toArray(?SyncStoreInterface $store = null): array;

    /**
     * Use the given serialization rules to serialize the entity and any nested
     * entities
     *
     * @param SyncSerializeRulesInterface<static> $rules
     * @return array<string,mixed>
     */
    public function toArrayWith(SyncSerializeRulesInterface $rules, ?SyncStoreInterface $store = null): array;

    /**
     * Get the entity's canonical location in the form of an array
     *
     * Inspired by JSON-LD.
     *
     * @param LinkType::* $type
     * @return array<string,int|string>
     */
    public function toLink(?SyncStoreInterface $store = null, int $type = LinkType::DEFAULT, bool $compact = true): array;

    /**
     * Get the entity's canonical location in the form of a URI
     *
     * Inspired by OData.
     */
    public function getUri(?SyncStoreInterface $store = null, bool $compact = true): string;
}
