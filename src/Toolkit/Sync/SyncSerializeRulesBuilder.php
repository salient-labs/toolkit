<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\AbstractBuilder;
use Closure;

/**
 * A fluent SyncSerializeRules factory
 *
 * @method $this dateFormatter(DateFormatterInterface|null $value) Date formatter used to serialize date and time values
 * @method $this includeMeta(bool|null $value = true) Serialize undeclared property values? (default: true)
 * @method $this sortByKey(bool|null $value = true) Sort serialized entities by key? (default: false)
 * @method $this maxDepth(int|null $value) Maximum depth of nested values (default: 99)
 * @method $this detectRecursion(bool|null $value = true) Detect recursion when serializing nested entities? (default: true)
 * @method $this recurseRules(bool|null $value = true) Apply path-based rules to nested instances of the entity? (default: true)
 * @method $this forSyncStore(bool|null $value = true) Are values being serialized for an entity store? (default: false)
 * @method $this includeCanonicalId(bool|null $value = true) Serialize canonical identifiers of sync entities? (default: false)
 * @method $this remove(array<array<(array{string,...}&array<(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|string|null>)|string>|(array{string,...}&array<(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|string|null>)|string> $value) Values to remove, e.g. `[OrgUnit::class => ['users']]` to remove `users` from `OrgUnit` objects
 * @method $this replace(array<array<(array{string,...}&array<(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|string|null>)|string>|(array{string,...}&array<(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|string|null>)|string> $value) Values to replace, e.g. `[User::class => [['org_unit', 'org_unit_id', fn($ou) => $ou->Id]]]` to replace `"org_unit" => $entity` with `"org_unit_id" => $entity->Id` in `User` objects
 *
 * @template TEntity of SyncEntityInterface
 *
 * @extends AbstractBuilder<SyncSerializeRules<TEntity>>
 *
 * @generated
 */
final class SyncSerializeRulesBuilder extends AbstractBuilder
{
    /**
     * @internal
     */
    protected static function getService(): string
    {
        return SyncSerializeRules::class;
    }

    /**
     * Entity to which the rules apply (required)
     *
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $value
     * @return static<T>
     */
    public function entity(string $value)
    {
        /** @var static<T> */
        return $this->withValueB(__FUNCTION__, $value);
    }

    /**
     * Inherit rules from another instance
     *
     * @template T of SyncEntityInterface
     *
     * @param SyncSerializeRules<T>|null $value
     * @return static<T>
     */
    public function inherit(?SyncSerializeRules $value)
    {
        /** @var static<T> */
        return $this->withValueB(__FUNCTION__, $value);
    }
}
