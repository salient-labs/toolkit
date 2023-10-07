<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concern\HasBuilder;
use Lkrms\Concern\HasMutator;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\ProvidesBuilder;
use Lkrms\Support\Catalog\NormaliserFlag;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncSerializeRules;
use Lkrms\Utility\Convert;
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
 *    required for {@see \Lkrms\Sync\Concept\SyncEntity} objects)
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
 * @template-covariant TEntity of ISyncEntity
 *
 * @property-read class-string<TEntity> $Entity The class name of the SyncEntity being serialized (required)
 * @property-read DateFormatter|null $DateFormatter Override the default date formatter (default: null)
 * @property-read bool|null $IncludeMeta Include undeclared property values? (default: true)
 * @property-read bool|null $SortByKey Sort arrays by key? (default: false)
 * @property-read int|null $MaxDepth Throw an exception when values are nested beyond this depth (default: 99)
 * @property-read bool|null $DetectRecursion Check for recursion? (default: true)
 * @property-read bool|null $RemoveCanonicalId Remove CanonicalId from sync entities? (default: true)
 * @property-read array<array<array<string|Closure>|string>|array<string|Closure>|string> $Remove Values to remove
 * @property-read array<array<array<string|Closure>|string>|array<string|Closure>|string> $Replace Values to replace with IDs
 * @property-read bool|null $RecurseRules Apply path-based rules to every instance of $Entity? (default: true)
 * @property-read int $Flags
 *
 * @implements ProvidesBuilder<SyncSerializeRulesBuilder<TEntity>>
 */
final class SyncSerializeRules implements ISyncSerializeRules, IReadable, IImmutable, ProvidesBuilder
{
    use TFullyReadable;
    use HasBuilder;
    use HasMutator;

    /**
     * Values are being serialized for an entity store
     */
    public const SYNC_STORE = 1;

    /**
     * The class name of the SyncEntity being serialized (required)
     *
     * @var class-string<TEntity>
     */
    protected $Entity;

    /**
     * Override the default date formatter (default: null)
     *
     * @var DateFormatter|null
     */
    protected $DateFormatter;

    /**
     * Include undeclared property values? (default: true)
     *
     * @var bool|null
     */
    protected $IncludeMeta;

    /**
     * Sort arrays by key? (default: false)
     *
     * @var bool|null
     */
    protected $SortByKey;

    /**
     * Throw an exception when values are nested beyond this depth (default: 99)
     *
     * Depth checks are not performed if `MaxDepth` is 0.
     *
     * @var int|null
     */
    protected $MaxDepth;

    /**
     * Check for recursion? (default: true)
     *
     * If it would be impossible for a circular reference to arise in an object
     * graph after applying {@see SyncSerializeRules::$Remove} and
     * {@see SyncSerializeRules::$Replace}, disable recursion detection to
     * improve performance and reduce memory consumption.
     *
     * @var bool|null
     */
    protected $DetectRecursion;

    /**
     * Remove CanonicalId from sync entities? (default: true)
     *
     * @var bool|null
     * @see \Lkrms\Sync\Concept\SyncEntity::$CanonicalId
     */
    protected $RemoveCanonicalId;

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
    protected $Remove;

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
    protected $Replace;

    /**
     * Apply path-based rules to every instance of $Entity? (default: true)
     *
     * @var bool|null
     */
    protected $RecurseRules;

    /**
     * @var int|null
     */
    protected $Flags;

    /**
     * @var array<string,array<string,array<array<array<string|Closure>|string>|array<string|Closure>|string>>>
     */
    private $RuleCache = [];

    /**
     * Path => true
     *
     * @var array<string,true>
     */
    private $RootPaths = [];

    /**
     * @var SyncIntrospector<TEntity>
     */
    private $Introspector;

    /**
     * @param class-string<TEntity> $entity
     * @param array<array<array<string|Closure>|string>|array<string|Closure>|string> $remove
     * @param array<array<array<string|Closure>|string>|array<string|Closure>|string> $replace
     * @param SyncSerializeRules<TEntity>|null $inherit
     */
    public function __construct(
        string $entity,
        ?DateFormatter $dateFormatter = null,
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
     * Apply rules from another instance
     *
     * Properties are copied from `$rules` to the receiving instance unless they
     * are `null`. Rules in {@see SyncSerializeRules::$Remove} and
     * {@see SyncSerializeRules::$Replace} are merged recursively. Precedence is
     * given to incoming rules if there are duplicates.
     *
     * @template T of TEntity
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
    public function getRemove(?string $class, ?string $untilClass, array $path): array
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
    public function getReplace(?string $class, ?string $untilClass, array $path): array
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
        $this->IncludeMeta = Convert::coalesce($merge->IncludeMeta, $base->IncludeMeta);
        $this->SortByKey = Convert::coalesce($merge->SortByKey, $base->SortByKey);
        $this->MaxDepth = Convert::coalesce($merge->MaxDepth, $base->MaxDepth);
        $this->DetectRecursion = Convert::coalesce($merge->DetectRecursion, $base->DetectRecursion);
        $this->RemoveCanonicalId = Convert::coalesce($merge->RemoveCanonicalId, $base->RemoveCanonicalId);
        $this->Remove = $this->flattenRules($base->Remove, $merge->Remove);
        $this->Replace = $this->flattenRules($base->Replace, $merge->Replace);
        $this->RecurseRules = Convert::coalesce($merge->RecurseRules, $base->RecurseRules);
        $this->Flags = Convert::coalesce($merge->Flags, $base->Flags);

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
        $key = Convert::sparseToString("\0", [$class, $untilClass, $path]);

        if (!is_null($rules = $this->RuleCache[$cacheKey][$key] ?? null)) {
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
            ARRAY_FILTER_USE_KEY
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
            } while (($class = get_parent_class($class)) && (!$untilClass || $class != $untilClass));
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
        return preg_replace_callback(
            '/[^].[]+/',
            fn($matches) => $this->Introspector->maybeNormalise($matches[0], NormaliserFlag::LAZY),
            $target
        );
    }

    /**
     * @template T of array{string,...}|string
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

    public function getDateFormatter(): ?DateFormatter
    {
        return $this->DateFormatter;
    }

    public function getIncludeMeta(): bool
    {
        return Convert::coalesce($this->IncludeMeta, true);
    }

    public function getSortByKey(): bool
    {
        return Convert::coalesce($this->SortByKey, false);
    }

    public function getMaxDepth(): ?int
    {
        return Convert::coalesce($this->MaxDepth, 99);
    }

    public function getDetectRecursion(): bool
    {
        return Convert::coalesce($this->DetectRecursion, true);
    }

    public function getRemoveCanonicalId(): bool
    {
        return Convert::coalesce($this->RemoveCanonicalId, true);
    }

    public function getRecurseRules(): bool
    {
        return Convert::coalesce($this->RecurseRules, true);
    }

    public function getFlags(): int
    {
        return Convert::coalesce($this->Flags, 0);
    }

    public function withIncludeMeta(?bool $value)
    {
        return $this->withPropertyValue('IncludeMeta', $value);
    }

    public function withSortByKey(?bool $value)
    {
        return $this->withPropertyValue('SortByKey', $value);
    }

    public function withRemoveCanonicalId(?bool $value)
    {
        return $this->withPropertyValue('RemoveCanonicalId', $value);
    }

    public function withMaxDepth(?int $value)
    {
        return $this->withPropertyValue('MaxDepth', $value);
    }

    /**
     * @inheritDoc
     */
    public static function getBuilder(): string
    {
        return SyncSerializeRulesBuilder::class;
    }
}
