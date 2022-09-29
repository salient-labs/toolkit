<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Closure;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Convert;

/**
 * Instructions for serializing nested objects without infinite recursion
 *
 * @property-read bool $DetectRecursion Check for recursion?
 * @property-read array<string,string[]> $DoNotSerialize Keys to remove from nested class arrays
 * @property-read array<string,string[]> $OnlySerializeId Keys to replace with identifiers in nested class arrays
 * @property-read callable|null $IdKeyCallback A callback that returns the key to use when a nested object is replaced with its identifier during serialization
 * @property-read bool $OnlySerializePlaceholders Replace nested objects with placeholders?
 */
final class SerializeRules implements IReadable, IImmutable
{
    use TFullyReadable;

    /**
     * Check for recursion?
     *
     * If it would be impossible for a circular reference to arise in an object
     * graph after applying {@see SerializeRules::$DoNotSerialize} and
     * {@see SerializeRules::$OnlySerializeId}, disable recursion detection to
     * improve performance and reduce memory consumption.
     *
     * @internal
     * @var bool
     */
    protected $DetectRecursion;

    /**
     * Keys to remove from nested class arrays
     *
     * For example, to delete the `users` key from any serialized `OrgUnit`
     * objects encountered while traversing an object graph:
     *
     * ```php
     * $rules->DoNotSerialize[OrgUnit::class][] = 'users';
     * ```
     *
     * @internal
     * @var array<string,string[]>
     */
    protected $DoNotSerialize = [];

    /**
     * Keys to replace with identifiers in nested class arrays
     *
     * For example, to replace `"org_unit" => {object}` with `"org_unit_id" =>
     * {object}->Id` in any serialized `User` objects encountered while
     * traversing an object graph:
     *
     * ```php
     * $rules->OnlySerializeId[User::class][] = 'org_unit';
     * ```
     *
     * Use {@see SerializeRules::$IdKeyCallback} to customise the name of the
     * replacement key.
     *
     * @internal
     * @var array<string,string[]>
     */
    protected $OnlySerializeId = [];

    /**
     * A callback that returns the key to use when a nested object is replaced
     * with its identifier during serialization
     *
     * For example:
     *
     * ```php
     * $rules->IdKeyCallback = fn($key): string => "_{$key}_id";
     * ```
     *
     * The default is to append `_id` to the key being replaced, e.g. `user`
     * becomes `user_id`.
     *
     * @internal
     * @var callable|null
     * ```php
     * callback(string $key): string
     * ```
     */
    protected $IdKeyCallback;

    /**
     * Replace nested objects with placeholders?
     *
     * An `OrgUnit` object, for example, might be represented as:
     *
     * ```php
     * [
     *     '@<namespace>.type' => OrgUnit::class,
     *     '@<namespace>.id'   => 17,
     * ]
     * ```
     *
     * @internal
     * @var bool
     */
    protected $OnlySerializePlaceholders = false;

    private $RuleCache = [];

    /**
     * @param array<string,string[]> $doNotSerialize
     * @param array<string,string[]> $onlySerializeId
     */
    public function __construct(?SerializeRules $inherit = null, bool $detectRecursion = true, array $doNotSerialize = [], array $onlySerializeId = [], ? callable $idKeyCallback = null, bool $onlySerializePlaceholders = false)
    {
        $this->DetectRecursion = $detectRecursion;
        $this->DoNotSerialize  = $doNotSerialize;
        $this->OnlySerializeId = $onlySerializeId;
        $this->IdKeyCallback   = $idKeyCallback;
        $this->OnlySerializePlaceholders = $onlySerializePlaceholders;

        if ($inherit)
        {
            $this->_apply($inherit, true);
        }
    }

    /**
     * Ensure classes inherit rules applied to their parents
     *
     */
    private function _mergeWithParentRules(string $class, ?string $untilClass, array $allRules, string $cacheKey): array
    {
        if (!is_null($rules = $this->RuleCache[$cacheKey][$class] ?? null))
        {
            return $rules;
        }

        $rules = [];
        do
        {
            if (!($_rules = $allRules[$class] ?? null))
            {
                continue;
            }
            array_push($rules, ...$_rules);
        }
        while (($class = get_parent_class($class)) && (!$untilClass || $class != $untilClass));

        return $this->RuleCache[$cacheKey][$class] = Convert::stringsToUniqueList($rules);
    }

    private function __clone()
    {
        $this->RuleCache = [];
    }

    /**
     * Apply rules from another instance
     *
     * Merged recursively:
     * - {@see SerializeRules::$DoNotSerialize}
     * - {@see SerializeRules::$OnlySerializeId}
     *
     * Copied from `$rules` unless already set:
     * - {@see SerializeRules::$IdKeyCallback}
     *
     * @return $this
     */
    public function apply(SerializeRules $rules)
    {
        $_this = clone $this;
        $_this->_apply($rules);

        return $_this;
    }

    private function _apply(SerializeRules $rules, bool $inherit = false)
    {
        [$base, $merge]        = $inherit ? [$rules, $this] : [$this, $rules];
        $this->DoNotSerialize  = array_merge_recursive($base->DoNotSerialize, $merge->DoNotSerialize);
        $this->OnlySerializeId = array_merge_recursive($base->OnlySerializeId, $merge->OnlySerializeId);
        $this->IdKeyCallback   = $this->IdKeyCallback ?: $rules->IdKeyCallback;
    }

    /**
     * Get a list of keys to remove from a serialized $class array
     *
     * @return string[]
     */
    public function getDoNotSerialize(string $class, ?string $untilClass = null): array
    {
        return $this->_mergeWithParentRules($class, $untilClass, $this->DoNotSerialize, __METHOD__);
    }

    /**
     * Get a list of keys where child entities in a serialized $class
     * array should be replaced with a scalar identifier
     *
     * @return string[]
     * @see SerializeRules::getSerializedIdKeyCallback()
     */
    public function getOnlySerializeId(string $class, ?string $untilClass = null): array
    {
        return $this->_mergeWithParentRules($class, $untilClass, $this->OnlySerializeId, __METHOD__);
    }

    /**
     * Get a closure that renames keys returned by getOnlySerializeId()
     *
     * @return Closure
     */
    public function getSerializedIdKeyCallback(): Closure
    {
        if (is_null($this->IdKeyCallback))
        {
            return fn(string $key): string => $key . "_id";
        }
        elseif (!($this->IdKeyCallback instanceof Closure))
        {
            return Closure::fromCallable($this->IdKeyCallback);
        }

        return $this->IdKeyCallback;
    }

    /**
     * Use a fluent interface to create a new SerializeRules object
     *
     */
    public static function build(): SerializeRulesBuilder
    {
        return new SerializeRulesBuilder();
    }

}
