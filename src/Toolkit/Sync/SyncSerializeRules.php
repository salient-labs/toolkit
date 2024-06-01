<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Buildable;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\NormaliserFlag;
use Salient\Contract\Core\SerializeRulesInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncSerializeRulesInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\Concern\HasBuilder;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Exception\LogicException;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Pcre;
use Salient\Sync\Support\SyncIntrospector;
use Closure;

/**
 * Rules applied when serializing a sync entity
 *
 * Rules can be applied to any combination of paths and class properties
 * reachable from the entity, e.g. for a `User` entity that normalises property
 * names:
 *
 * ```php
 * <?php
 * [
 *     // Applied to '.Manager.OrgUnit'
 *     '.manager.org_unit',
 *     User::class => [
 *         // Ignored for '.Manager.OrgUnit' because a path-based rule applies,
 *         // but applied to '.OrgUnit', '.Staff[].OrgUnit', ...
 *         'org_unit',
 *     ],
 * ];
 * ```
 *
 * Path-based rules apply to specific nodes in the object graph below the entity
 * (only the `OrgUnit` of the user's manager in the example above), whereas
 * class-based rules apply to every appearance of a class in the entity's object
 * graph (the `OrgUnit` of every `User` object, including the one being
 * serialized, in this example).
 *
 * Each rule must be either a `string` containing the path or key to act upon,
 * or an `array` with up to 3 values:
 *
 * 1. the path or key to act upon (`string`; required; must be the first value)
 * 2. a new key for the value (`int|string`; optional)
 * 3. a closure to return a new value for the key (`Closure($value, $store,
 *    $rules): mixed`; optional)
 *
 * Optional values may be omitted.
 *
 * If multiple rules apply to the same key, path-based rules take precedence
 * over class-based ones, then later rules take precedence over earlier ones:
 *
 * ```php
 * <?php
 * [
 *     // Ignored because it applies to the same path as the next rule
 *     '.manager.org_unit',
 *     // Applied to '.Manager.OrgUnit'
 *     [
 *         '.manager.org_unit',
 *         'org_unit_id',
 *         fn($ou) => $ou->Id,
 *     ],
 *     User::class => [
 *         // Ignored because it applies to the same property as the next rule
 *         'org_unit',
 *         // Ignored for '.Manager.OrgUnit' because a path-based rule applies,
 *         // but applied to '.OrgUnit', '.Staff[].OrgUnit', ...
 *         ['org_unit', 'org_unit_id'],
 *     ],
 * ];
 * ```
 *
 * @template TEntity of SyncEntityInterface
 *
 * @implements SyncSerializeRulesInterface<TEntity>
 * @implements Buildable<SyncSerializeRulesBuilder<TEntity>>
 */
final class SyncSerializeRules implements SyncSerializeRulesInterface, Buildable
{
    private const TYPE_REMOVE = 0;
    private const TYPE_REPLACE = 1;

    /** @use HasBuilder<SyncSerializeRulesBuilder<TEntity>> */
    use HasBuilder;
    use HasImmutableProperties {
        withPropertyValue as with;
    }

    private ContainerInterface $App;
    /** @var class-string<TEntity> */
    private string $Entity;
    private ?DateFormatterInterface $DateFormatter;
    private ?bool $IncludeMeta;
    private ?bool $SortByKey;
    private ?int $MaxDepth;
    private ?bool $DetectRecursion;
    private ?bool $RecurseRules;
    private ?bool $ForSyncStore;
    private ?bool $IncludeCanonicalId;
    /** @var array<array<array<array{string,...}|string>|array{string,...}|string>> */
    private array $Remove;
    /** @var array<array<array<array{string,...}|string>|array{string,...}|string>> */
    private array $Replace;
    /** @var array<self::TYPE_*,array<0|string,array<array{string,int|string|null,(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|null}>>> */
    private array $FlattenCache;
    /** @var array{0:array<string,array<string,string>>,1:array<string,array<string,array{int|string|null,(Closure(mixed $value, SyncStoreInterface|null $store=): mixed)|null}>>} */
    private array $CompileCache;
    /** @var array<string,true> */
    private array $EntityPathIndex;
    /** @var SyncIntrospector<TEntity> */
    private SyncIntrospector $Introspector;

    /**
     * Creates a new SyncSerializeRules object
     *
     * @param class-string<TEntity> $entity Entity to which the rules apply (required)
     * @param DateFormatterInterface|null $dateFormatter Date formatter used to serialize date and time values
     * @param bool|null $includeMeta Serialize undeclared property values? (default: true)
     * @param bool|null $sortByKey Sort serialized entities by key? (default: false)
     * @param int|null $maxDepth Maximum depth of nested values (default: 99)
     * @param bool|null $detectRecursion Detect recursion when serializing nested entities? (default: true)
     * @param bool|null $recurseRules Apply path-based rules to nested instances of the entity? (default: true)
     * @param bool|null $forSyncStore Are values being serialized for an entity store? (default: false)
     * @param bool|null $includeCanonicalId Serialize canonical identifiers of sync entities? (default: false)
     * @param array<array<(array{string,...}&array<(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|int|string|null>)|string>|(array{string,...}&array<(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|int|string|null>)|string> $remove Values to remove, e.g. `[OrgUnit::class => ['users']]` to remove `users` from `OrgUnit` objects
     * @param array<array<(array{string,...}&array<(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|int|string|null>)|string>|(array{string,...}&array<(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|int|string|null>)|string> $replace Values to replace, e.g. `[User::class => [['org_unit', 'org_unit_id', fn($ou) => $ou->Id]]]` to replace `"org_unit" => $entity` with `"org_unit_id" => $entity->Id` in `User` objects
     * @param SyncSerializeRules<TEntity>|null $inherit Inherit rules from another instance
     */
    public function __construct(
        ContainerInterface $app,
        string $entity,
        ?DateFormatterInterface $dateFormatter = null,
        ?bool $includeMeta = null,
        ?bool $sortByKey = null,
        ?int $maxDepth = null,
        ?bool $detectRecursion = null,
        ?bool $recurseRules = null,
        ?bool $forSyncStore = null,
        ?bool $includeCanonicalId = null,
        array $remove = [],
        array $replace = [],
        ?SyncSerializeRules $inherit = null
    ) {
        $this->App = $app;
        $this->Entity = $entity;
        $this->DateFormatter = $dateFormatter;
        $this->IncludeMeta = $includeMeta;
        $this->SortByKey = $sortByKey;
        $this->MaxDepth = $maxDepth;
        $this->DetectRecursion = $detectRecursion;
        $this->RecurseRules = $recurseRules;
        $this->ForSyncStore = $forSyncStore;
        $this->IncludeCanonicalId = $includeCanonicalId;
        $this->Remove[] = $remove;
        $this->Replace[] = $replace;

        if ($inherit) {
            $this->applyRules($inherit, true);
        }
    }

    private function __clone()
    {
        unset($this->FlattenCache);
        unset($this->CompileCache);
        unset($this->EntityPathIndex);
        unset($this->Introspector);
    }

    /**
     * @inheritDoc
     */
    public function getContainer(): ContainerInterface
    {
        return $this->App;
    }

    /**
     * @template T of TEntity
     *
     * @param static<T> $rules
     * @return static<T>
     */
    public function merge(SerializeRulesInterface $rules): SerializeRulesInterface
    {
        /** @var static<T> */
        $clone = $this->clone();
        $clone->applyRules($rules);
        return $clone;
    }

    /**
     * @param static $rules
     */
    private function applyRules(SerializeRulesInterface $rules, bool $inherit = false): void
    {
        if ($inherit) {
            $base = $rules;
            $merge = $this;
        } else {
            $base = $this;
            $merge = $rules;
        }

        if (!is_a($merge->Entity, $base->Entity, true)) {
            throw new LogicException(sprintf(
                '%s does not inherit %s',
                $merge->Entity,
                $base->Entity,
            ));
        }

        $this->Entity = $merge->Entity;
        $this->DateFormatter = $merge->DateFormatter ?? $base->DateFormatter;
        $this->IncludeMeta = $merge->IncludeMeta ?? $base->IncludeMeta;
        $this->SortByKey = $merge->SortByKey ?? $base->SortByKey;
        $this->MaxDepth = $merge->MaxDepth ?? $base->MaxDepth;
        $this->DetectRecursion = $merge->DetectRecursion ?? $base->DetectRecursion;
        $this->RecurseRules = $merge->RecurseRules ?? $base->RecurseRules;
        $this->ForSyncStore = $merge->ForSyncStore ?? $base->ForSyncStore;
        $this->IncludeCanonicalId = $merge->IncludeCanonicalId ?? $base->IncludeCanonicalId;
        $this->Remove = [...$base->Remove, ...$merge->Remove];
        $this->Replace = [...$base->Replace, ...$merge->Replace];
    }

    /**
     * @inheritDoc
     */
    public function getRemovableKeys(?string $class, ?string $baseClass, array $path): array
    {
        return $this->compileRules($class, $baseClass, $path, $this->getRemove(), self::TYPE_REMOVE);
    }

    /**
     * @inheritDoc
     */
    public function getReplaceableKeys(?string $class, ?string $baseClass, array $path): array
    {
        return $this->compileRules($class, $baseClass, $path, $this->getReplace(), self::TYPE_REPLACE);
    }

    /**
     * @return array<0|string,array<array{string,int|string|null,(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|null}>>
     */
    private function getRemove(): array
    {
        return $this->FlattenCache[self::TYPE_REMOVE] ??= $this->flattenRules(...$this->Remove);
    }

    /**
     * @return array<0|string,array<array{string,int|string|null,(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|null}>>
     */
    private function getReplace(): array
    {
        return $this->FlattenCache[self::TYPE_REPLACE] ??= $this->flattenRules(...$this->Replace);
    }

    /**
     * Merge and normalise rules and property names
     *
     * @param array<array<array{string,...}|string>|array{string,...}|string> ...$merge
     * @return array<0|string,array<array{string,int|string|null,(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|null}>>
     */
    private function flattenRules(array ...$merge): array
    {
        $this->Introspector ??= SyncIntrospector::get($this->Entity);

        foreach ($merge as $array) {
            foreach ($array as $key => $rule) {
                if (is_int($key)) {
                    /** @var array{string,...}|string $rule */
                    $target = $this->normaliseTarget($this->getTarget($rule));
                    $rule = $this->normaliseRule($rule, $target);
                    $paths[$target] = $rule;
                    continue;
                }

                /** @var array<array{string,...}|string> $rule */
                foreach ($rule as $_rule) {
                    $target = $this->normaliseTarget($this->getTarget($_rule));
                    $_rule = $this->normaliseRule($_rule, $target);
                    $classes[$key][$target] = $_rule;
                }
            }
        }

        // Return path-based rules followed by class-based rules
        return array_map(
            fn(array $rules) => array_values($rules),
            (isset($paths) ? [0 => $paths] : []) + ($classes ?? []),
        );
    }

    /**
     * @param string[] $path
     * @param array<0|string,array<array{string,int|string|null,(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|null}>> $rules
     * @param self::TYPE_* $ruleType
     * @return ($ruleType is 0 ? array<string,string> : array<string,array{int|string|null,(Closure(mixed $value, SyncStoreInterface|null $store=): mixed)|null}>)
     */
    private function compileRules(
        ?string $class,
        ?string $untilClass,
        array $path,
        array $rules,
        int $ruleType
    ): array {
        $key = '.' . implode('.', $path);
        $cacheKey = Arr::implode("\0", [$class, $untilClass, $key]);

        if (isset($this->CompileCache[$ruleType][$cacheKey])) {
            return $this->CompileCache[$ruleType][$cacheKey];
        }

        $keys = [$key];

        /** @todo Same for inherited entities? */
        if ($this->getRecurseRules()) {
            // If an instance of `$this->Entity` is found at '.path.to.key', add
            // it to `$this->EntityPath`
            if ($path && $class === $this->Entity) {
                $this->EntityPathIndex[$key] = true;
                $keys[] = '.';
            } else {
                // Then, if `$key` is '.path.to.key.of.child', add '.of.child'
                // to `$keys`
                $parent = $path;
                $child = [];
                $parts = count($parent);
                while ($parts-- > 1) {
                    array_unshift($child, array_pop($parent));
                    if (isset($this->EntityPathIndex['.' . implode('.', $parent)])) {
                        $keys[] = '.' . implode('.', $child);
                    }
                }
            }
        }

        // If '.path.to' is in `$keys`, convert path-based rules like:
        // - `['.path.to.key', 'key_id', fn($value) => $value['id']]`
        //
        // to `$pathRules` entries like:
        // - `['key', 'key_id', fn($value) => $value['id']]`
        $pathRules = [];
        if (isset($rules[0])) {
            $keys = array_fill_keys($keys, true);
            foreach ($rules[0] as $key => $rule) {
                $target = $rule[0];
                if (isset($keys[$this->getTargetParent($target)])) {
                    $rule[0] = $this->getTargetProperty($target);
                    $pathRules[] = $rule;
                }
            }
        }

        // Copy class-based rules applied to `$class` and its parents to
        // `$classRules`:
        $classRules = [];
        if ($class !== null) {
            while ($untilClass === null || $class !== $untilClass) {
                if (isset($rules[$class])) {
                    // Give precedence to rules applied to subclasses
                    array_unshift($classRules, ...$rules[$class]);
                }
                $class = get_parent_class($class);
                if ($class === false) {
                    break;
                }
            }
        }

        // Return the highest-precedence rule for each key
        $rules = [];
        foreach ([...$classRules, ...$pathRules] as $rule) {
            $target = array_shift($rule);
            $rules[$target] = $rule;
        }

        if ($ruleType === self::TYPE_REMOVE) {
            $keys = array_keys($rules);
            $rules = array_combine($keys, $keys);
            return $this->CompileCache[$ruleType][$cacheKey] = $rules;
        }

        foreach ($rules as $key => $rule) {
            if ($rule[1] !== null) {
                $closure = $rule[1];
                $rule[1] = fn($value, ?SyncStoreInterface $store = null) =>
                    $closure($value, $store, $this);
            }
            $compiled[$key] = $rule;
        }

        return $this->CompileCache[$ruleType][$cacheKey] = $compiled ?? [];
    }

    /**
     * @param array{string,...}|string $rule
     */
    private function getTarget($rule): string
    {
        return is_array($rule) ? reset($rule) : $rule;
    }

    private function normaliseTarget(string $target): string
    {
        return Pcre::replaceCallback(
            '/[^].[]++/',
            fn(array $matches): string =>
                $this->Introspector->maybeNormalise($matches[0], NormaliserFlag::LAZY),
            $target,
        );
    }

    /**
     * @param array{string,...}|string $rule
     * @return array{string,int|string|null,(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<TEntity>): mixed)|null}
     */
    private function normaliseRule($rule, string $target)
    {
        $normalised = [$target, null, null];
        if (!is_array($rule)) {
            return $normalised;
        }
        /** @var array<Closure|int|string|null> */
        $rule = array_slice($rule, 1);
        foreach ($rule as $value) {
            if ($value === null) {
                continue;
            }
            if ($value instanceof Closure) {
                $normalised[2] = $value;
                continue;
            }
            $normalised[1] = is_string($value)
                ? $this->Introspector->maybeNormalise($value, NormaliserFlag::LAZY)
                : $value;
        }
        return $normalised;
    }

    private function getTargetParent(string $target): string
    {
        return ((substr($target, -2) === '[]')
            ? substr($target, 0, -2)
            : substr($target, 0, max(0, strrpos('.' . $target, '.') - 1))) ?: '.';
    }

    private function getTargetProperty(string $target): string
    {
        return (substr($target, -2) === '[]')
            ? '[]'
            : substr((string) strrchr('.' . $target, '.'), 1);
    }

    /**
     * @inheritDoc
     */
    public function getEntity(): string
    {
        return $this->Entity;
    }

    /**
     * @inheritDoc
     */
    public function getDateFormatter(): ?DateFormatterInterface
    {
        return $this->DateFormatter;
    }

    /**
     * @inheritDoc
     */
    public function getIncludeMeta(): bool
    {
        return $this->IncludeMeta ?? true;
    }

    /**
     * @inheritDoc
     */
    public function getSortByKey(): bool
    {
        return $this->SortByKey ?? false;
    }

    /**
     * @inheritDoc
     */
    public function getMaxDepth(): int
    {
        return $this->MaxDepth ?? 99;
    }

    /**
     * @inheritDoc
     */
    public function getDetectRecursion(): bool
    {
        return $this->DetectRecursion ?? true;
    }

    /**
     * @inheritDoc
     */
    public function getRecurseRules(): bool
    {
        return $this->RecurseRules ?? true;
    }

    /**
     * @inheritDoc
     */
    public function getForSyncStore(): bool
    {
        return $this->ForSyncStore ?? false;
    }

    /**
     * @inheritDoc
     */
    public function getIncludeCanonicalId(): bool
    {
        return $this->IncludeCanonicalId ?? false;
    }

    /**
     * @inheritDoc
     */
    public function withDateFormatter(?DateFormatterInterface $formatter)
    {
        return $this->with('DateFormatter', $formatter);
    }

    /**
     * @inheritDoc
     */
    public function withIncludeMeta(?bool $include = true)
    {
        return $this->with('IncludeMeta', $include);
    }

    /**
     * @inheritDoc
     */
    public function withSortByKey(?bool $sort = true)
    {
        return $this->with('SortByKey', $sort);
    }

    /**
     * @inheritDoc
     */
    public function withMaxDepth(?int $depth)
    {
        return $this->with('MaxDepth', $depth);
    }

    /**
     * @inheritDoc
     */
    public function withDetectRecursion(?bool $detect = true)
    {
        return $this->with('DetectRecursion', $detect);
    }

    /**
     * @inheritDoc
     */
    public function withRecurseRules(?bool $recurse = true)
    {
        return $this->with('RecurseRules', $recurse);
    }

    /**
     * @inheritDoc
     */
    public function withForSyncStore(?bool $forSyncStore = true)
    {
        return $this->with('ForSyncStore', $forSyncStore);
    }

    /**
     * @inheritDoc
     */
    public function withIncludeCanonicalId(?bool $include = true)
    {
        return $this->with('IncludeCanonicalId', $include);
    }
}
