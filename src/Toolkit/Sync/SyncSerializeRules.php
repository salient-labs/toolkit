<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Buildable;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\Immutable;
use Salient\Contract\Core\NormaliserFlag;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncSerializeRulesInterface;
use Salient\Core\Concern\HasBuilder;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Pcre;
use Salient\Sync\Support\SyncIntrospector;
use Closure;
use LogicException;

/**
 * Instructions for serializing nested sync entities
 *
 * Rules in {@see SyncSerializeRules::$Remove} and
 * {@see SyncSerializeRules::$Replace} can be defined for any combination of
 * paths and class properties:
 *
 * ```php
 * <?php
 * $rules = [
 *     // The following paths refer to the same node
 *     '.manager.org_unit',
 *     '.Manager.OrgUnit',
 *
 *     // And these resolve to the same property
 *     User::class => [
 *         'OrgUnit',
 *         'org_unit',
 *     ],
 * ];
 * ```
 *
 * Path-based rules allow particular nodes in an object graph to be acted upon
 * (only the `org_unit` of the user's manager in the example above), whereas
 * class-based rules apply to every instance of a class encountered in an object
 * graph (the `OrgUnit` of every `User` object, including the one being
 * serialized, in this case).
 *
 * Each rule must be either a `string` containing the path or key to act upon,
 * or an `array` with up to 3 elements:
 *
 * 1. the path or key to act upon (`string`; required)
 * 2. a new name for the key (`int|string`; optional)
 * 3. a closure to return a new value for the key (`Closure`; optional; not
 *    required for {@see AbstractSyncEntity} objects)
 *
 * Optional elements may be omitted.
 *
 * If multiple rules apply to the same key, path-based rules take precedence
 * over class-based ones, and later rules take precedence over earlier ones.
 *
 * ```php
 * <?php
 * $rules = [
 *     '.manager.org_unit',
 *     [
 *         '.manager.org_unit',
 *         'org_unit_id',
 *         fn($ou) => $ou->Id,
 *     ],
 *     User::class => [
 *         'org_unit',
 *         ['org_unit', 'org_unit_id'],
 *     ],
 * ];
 * ```
 *
 * @template-covariant TEntity of SyncEntityInterface
 *
 * @implements Buildable<SyncSerializeRulesBuilder<TEntity>>
 */
final class SyncSerializeRules implements
    SyncSerializeRulesInterface,
    Buildable,
    Immutable
{
    /** @use HasBuilder<SyncSerializeRulesBuilder<TEntity>> */
    use HasBuilder;
    use HasImmutableProperties;

    /**
     * The object's service container
     */
    protected ContainerInterface $App;

    /**
     * The class name of the AbstractSyncEntity being serialized (required)
     *
     * @var class-string<TEntity>
     */
    protected string $Entity;

    /**
     * Override the default date formatter (default: null)
     */
    protected ?DateFormatterInterface $DateFormatter;

    /**
     * Include undeclared property values? (default: true)
     */
    protected ?bool $IncludeMeta;

    /**
     * Sort arrays by key? (default: false)
     */
    protected ?bool $SortByKey;

    /**
     * Throw an exception when values are nested beyond this depth (default: 99)
     *
     * Depth checks are not performed if `MaxDepth` is 0.
     */
    protected ?int $MaxDepth;

    /**
     * Check for recursion? (default: true)
     *
     * If it would be impossible for a circular reference to arise in an object
     * graph after applying {@see SyncSerializeRules::$Remove} and
     * {@see SyncSerializeRules::$Replace}, disable recursion detection to
     * improve performance and reduce memory consumption.
     */
    protected ?bool $DetectRecursion;

    /**
     * Remove CanonicalId from sync entities? (default: true)
     *
     * @see AbstractSyncEntity::$CanonicalId
     */
    protected ?bool $RemoveCanonicalId;

    /**
     * Values to remove
     *
     * For example, to remove `users` from any serialized `OrgUnit` objects
     * encountered while traversing an object graph:
     *
     * ```php
     * <?php
     * $rules = [
     *     OrgUnit::class => [
     *         'users',
     *     ],
     * ];
     * ```
     *
     * @var array<array<array<string|Closure>|string>|array<string|Closure>|string>
     */
    protected array $Remove;

    /**
     * Values to replace with IDs
     *
     * For example, to replace `"org_unit" => {object}` with `"org_unit_id" =>
     * {object}->Id` in any serialized `User` objects encountered while
     * traversing an object graph:
     *
     * ```php
     * <?php
     * $rules = [
     *     User::class => [
     *         ['org_unit', 'org_unit_id', fn($ou) => $ou->Id],
     *     ],
     * ];
     * ```
     *
     * @var array<array<array<string|Closure>|string>|array<string|Closure>|string>
     */
    protected array $Replace;

    /**
     * Apply path-based rules to every instance of $Entity? (default: true)
     */
    protected ?bool $RecurseRules;

    protected ?int $Flags;

    /**
     * @var array<string,array<string,array<array<array<string|Closure>|string>|array<string|Closure>|string>>>
     */
    private array $RuleCache = [];

    /**
     * Path => true
     *
     * @var array<string,true>
     */
    private array $RootPaths = [];

    /**
     * @var SyncIntrospector<TEntity>
     */
    private SyncIntrospector $Introspector;

    /**
     * @param class-string<TEntity> $entity
     * @param array<array<array<string|Closure>|string>|array<string|Closure>|string> $remove
     * @param array<array<array<string|Closure>|string>|array<string|Closure>|string> $replace
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
        ?bool $removeCanonicalId = null,
        array $remove = [],
        array $replace = [],
        ?bool $recurseRules = null,
        ?int $flags = null,
        ?SyncSerializeRules $inherit = null
    ) {
        $this->App = $app;
        $this->Entity = $entity;
        $this->DateFormatter = $dateFormatter;
        $this->IncludeMeta = $includeMeta;
        $this->SortByKey = $sortByKey;
        $this->MaxDepth = $maxDepth;
        $this->DetectRecursion = $detectRecursion;
        $this->RemoveCanonicalId = $removeCanonicalId;
        $this->Remove = $remove;
        $this->Replace = $replace;
        $this->RecurseRules = $recurseRules;
        $this->Flags = $flags;

        $this->Introspector = SyncIntrospector::get($this->Entity);

        if ($inherit) {
            $this->applyRules($inherit, true);
            return;
        }

        $this->Remove = $this->flattenRules($this->Remove);
        $this->Replace = $this->flattenRules($this->Replace);
    }

    /**
     * @inheritDoc
     */
    public function getApp(): ContainerInterface
    {
        return $this->App;
    }

    /**
     * @inheritDoc
     */
    public function getContainer(): ContainerInterface
    {
        return $this->App;
    }

    /**
     * Apply rules from another instance
     *
     * Properties are copied from `$rules` to the receiving instance unless they
     * are `null`. Rules in {@see SyncSerializeRules::$Remove} and
     * {@see SyncSerializeRules::$Replace} are merged recursively. Precedence is
     * given to incoming rules if there are duplicates.
     *
     * @template T of TEntity
     *
     * @param SyncSerializeRules<T> $rules
     * @return SyncSerializeRules<TEntity>
     */
    public function apply(SyncSerializeRules $rules)
    {
        $clone = clone $this;
        return $clone->applyRules($rules);
    }

    /**
     * Get a list of keys to remove from a serialized $class encountered at
     * $path
     *
     * @param string[] $path
     * @return array<string,string>
     */
    public function getRemoveFrom(?string $class, ?string $untilClass, array $path): array
    {
        return array_map(
            fn($rule) => is_array($rule) ? reset($rule) : $rule,
            $this->compileRules($class, $untilClass, $path, $this->Remove, __FUNCTION__)
        );
    }

    /**
     * Get a list of value replacement rules for a serialized $class encountered
     * at $path
     *
     * @param string[] $path
     * @return array<string,array<array<string|Closure>|string>|array<string|Closure>|string>
     */
    public function getReplaceIn(?string $class, ?string $untilClass, array $path): array
    {
        return $this->compileRules($class, $untilClass, $path, $this->Replace, __FUNCTION__);
    }

    /**
     * @param SyncSerializeRules<TEntity> $rules
     * @return $this
     */
    private function applyRules(SyncSerializeRules $rules, bool $inherit = false)
    {
        [$base, $merge] = $inherit ? [$rules, $this] : [$this, $rules];

        if (!is_a($merge->Entity, $base->Entity, true)) {
            throw new LogicException(sprintf('Not a subclass of %s: %s', $base->Entity, $merge->Entity));
        }

        $this->Entity = $merge->Entity;
        $this->DateFormatter = $merge->DateFormatter ?: $base->DateFormatter;
        $this->IncludeMeta = Get::coalesce($merge->IncludeMeta, $base->IncludeMeta);
        $this->SortByKey = Get::coalesce($merge->SortByKey, $base->SortByKey);
        $this->MaxDepth = Get::coalesce($merge->MaxDepth, $base->MaxDepth);
        $this->DetectRecursion = Get::coalesce($merge->DetectRecursion, $base->DetectRecursion);
        $this->RemoveCanonicalId = Get::coalesce($merge->RemoveCanonicalId, $base->RemoveCanonicalId);
        $this->Remove = $this->flattenRules($base->Remove, $merge->Remove);
        $this->Replace = $this->flattenRules($base->Replace, $merge->Replace);
        $this->RecurseRules = Get::coalesce($merge->RecurseRules, $base->RecurseRules);
        $this->Flags = Get::coalesce($merge->Flags, $base->Flags);

        return $this;
    }

    /**
     * Merge, remove duplicates and normalise field/property names
     *
     * @param array<array<array<string|Closure>|string>|array<string|Closure>|string> $base
     * @param array<array<array<string|Closure>|string>|array<string|Closure>|string> $merge
     * @return array<array<array<string|Closure>|string>|array<string|Closure>|string>
     */
    private function flattenRules(array $base, array ...$merge): array
    {
        $paths = $classes = [];
        foreach ([$base, ...$merge] as $array) {
            foreach ($array as $key => $rule) {
                if (is_int($key)) {
                    /** @var array{string,...}|string $rule */
                    $target = $this->normaliseTarget($this->getTarget($rule));
                    $rule = $this->setTarget($rule, $target);

                    $paths[$target] = $rule;

                    continue;
                }
                foreach ($rule as $_rule) {
                    /** @var array{string,...}|string $_rule */
                    $target = $this->normaliseTarget($this->getTarget($_rule));
                    $_rule = $this->setTarget($_rule, $target);

                    $classes[$key][$target] = $_rule;
                }
            }
        }

        // Return path-based rules followed by class-based rules
        return array_values($paths)
            + array_map(fn(array $rules) => array_values($rules), $classes);
    }

    /**
     * Use $allRules to compile a list of rules that apply to $class and its
     * ancestors up to (but not including) $untilClass, and to instances of
     * $class at $path
     *
     * @param string[] $path
     * @param array<array<array<string|Closure>|string>|array<string|Closure>|string> $allRules
     * @return array<string,array<array<string|Closure>|string>|array<string|Closure>|string>
     */
    private function compileRules(
        ?string $class,
        ?string $untilClass,
        array $path,
        array $allRules,
        string $cacheKey
    ): array {
        $depth = count($path);
        $path = '.' . implode('.', $path);
        $key = Arr::implode("\0", [$class, $untilClass, $path]);

        if (null !== ($rules = $this->RuleCache[$cacheKey][$key] ?? null)) {
            return $rules;
        }

        if ($this->getRecurseRules()) {
            $_depth = $depth;
            $_path = $path;
            $paths = [$path];

            // If `$this->RootPaths['.path.to.key']` is `true`, an instance of
            // the class these rules apply to (`$this->Entity`) was found at
            // '.path.to.key' (relative to another instance of the same class).
            //
            // Then, if `$path` is '.path.to.key.of.child', the following loop
            // adds '.of.child' to `$paths`.
            while ($_depth-- > 1) {
                $_path = substr($_path, 0, strrpos($_path, '.'));
                if ($this->RootPaths[$_path] ?? false) {
                    $paths[] = substr($path, strlen($_path));
                }
            }

            if ($depth && $class === $this->Entity) {
                if (!($this->RootPaths[$path] ?? false)) {
                    $this->RootPaths[$path] = true;
                }
                $paths[] = '.';
            }
        }

        // Extract rules like:
        // - [0 => ".path.to.key"]
        // - [0 => [".path.to.key", "key_id", fn($value) => $value["id"]]]
        /** @var array<array{string,...}|string> $pathRules */
        $pathRules = array_filter(
            $allRules,
            fn($key) => is_int($key),
            \ARRAY_FILTER_USE_KEY
        );
        // Discard if ".path.to" is not in $paths
        $pathRules = array_filter(
            $pathRules,
            fn($rule) => in_array($this->getPath($this->getTarget($rule)), $paths ?? [$path])
        );
        // Remove ".path.to" from the remaining rules
        $pathRules = array_map(
            fn($rule) => $this->setTarget($rule, $this->getKey($this->getTarget($rule))),
            $pathRules
        );

        // And rules like:
        // - [<Entity>::class => "key"]
        // - [<Entity>::class => ["key", "key_id", fn($value) => $value["id"]]]
        $classRules = [];
        if ($class) {
            do {
                if (!($rules = $allRules[$class] ?? null)) {
                    continue;
                }
                array_push($classRules, ...$rules);
            } while (($class = get_parent_class($class)) && (!$untilClass || $class !== $untilClass));
        }

        // Only return the highest-precedence rule for each key
        $rules = [];
        foreach ([...$classRules, ...$pathRules] as $rule) {
            $target = $this->getTarget($rule);
            $rules[$target] = $rule;
        }

        return $this->RuleCache[$cacheKey][$key] = $rules;
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
            '/[^].[]+/',
            fn($matches) => $this->Introspector->maybeNormalise($matches[0], NormaliserFlag::LAZY),
            $target
        );
    }

    /**
     * @template T of array{string,...}|string
     *
     * @param T $rule
     * @return T
     */
    private function setTarget($rule, string $target)
    {
        if (is_array($rule)) {
            $rule = array_values($rule);
            $rule[0] = $target;

            return $rule;
        }

        return $target;
    }

    /**
     * Remove the last component of $path
     */
    private function getPath(string $path): string
    {
        return ((substr($path, -2) === '[]')
            ? substr($path, 0, -2)
            : substr($path, 0, max(0, strrpos('.' . $path, '.') - 1))) ?: '.';
    }

    /**
     * Get the last component of $path
     */
    private function getKey(string $path): string
    {
        return (substr($path, -2) === '[]')
            ? '[]'
            : substr(strrchr('.' . $path, '.'), 1);
    }

    private function __clone()
    {
        $this->RuleCache = [];
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
        return Get::coalesce($this->IncludeMeta, true);
    }

    /**
     * @inheritDoc
     */
    public function getSortByKey(): bool
    {
        return Get::coalesce($this->SortByKey, false);
    }

    /**
     * @inheritDoc
     */
    public function getMaxDepth(): ?int
    {
        return Get::coalesce($this->MaxDepth, 99);
    }

    /**
     * @inheritDoc
     */
    public function getDetectRecursion(): bool
    {
        return Get::coalesce($this->DetectRecursion, true);
    }

    /**
     * @inheritDoc
     */
    public function getRemoveCanonicalId(): bool
    {
        return Get::coalesce($this->RemoveCanonicalId, true);
    }

    public function getRecurseRules(): bool
    {
        return Get::coalesce($this->RecurseRules, true);
    }

    /**
     * @inheritDoc
     */
    public function getFlags(): int
    {
        return Get::coalesce($this->Flags, 0);
    }

    /**
     * @return array<array<array<string|Closure>|string>|array<string|Closure>|string>
     */
    public function getRemove(): array
    {
        return $this->Remove;
    }

    /**
     * @return array<array<array<string|Closure>|string>|array<string|Closure>|string>
     */
    public function getReplace(): array
    {
        return $this->Replace;
    }

    /**
     * @inheritDoc
     */
    public function withIncludeMeta(?bool $value)
    {
        return $this->withPropertyValue('IncludeMeta', $value);
    }

    /**
     * @inheritDoc
     */
    public function withSortByKey(?bool $value)
    {
        return $this->withPropertyValue('SortByKey', $value);
    }

    /**
     * @inheritDoc
     */
    public function withRemoveCanonicalId(?bool $value)
    {
        return $this->withPropertyValue('RemoveCanonicalId', $value);
    }

    /**
     * @inheritDoc
     */
    public function withMaxDepth(?int $value)
    {
        return $this->withPropertyValue('MaxDepth', $value);
    }
}
