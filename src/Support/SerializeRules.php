<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Closure;

/**
 * Instructions for serializing nested objects without infinite recursion
 *
 */
class SerializeRules
{
    /**
     * Check for recursion?
     *
     * If it would be impossible for a circular reference to arise in an object
     * graph after applying {@see SerializeRules::$DoNotSerialize} and
     * {@see SerializeRules::$OnlySerializeId}, recursion detection can be
     * disabled to improve performance and reduce memory consumption.
     *
     * @var bool
     */
    public $DetectRecursion = true;

    /**
     * Keys to remove from the serialized forms of nested classes
     *
     * For example, to delete the `users` key from any serialized `OrgUnit`
     * objects encountered while traversing an object graph:
     *
     * ```php
     * $rules->DoNotSerialize[OrgUnit::class][] = 'users';
     * ```
     *
     * @var array<string,string[]>
     */
    public $DoNotSerialize = [];

    /**
     * Keys to replace with identifiers in the serialized forms of nested
     * classes
     *
     * For example, to replace `"org_unit" => {object}` with `"org_unit_id" =>
     * {object}->Id` in any serialized `User` objects encountered while
     * traversing an object graph:
     *
     * ```php
     * $rules->OnlySerializeId[User::class][] = 'org_unit';
     * ```
     *
     * Use {@see SerializeRules::$GetSerializedIdKey} to customise the name of
     * the replacement key.
     *
     * @var array<string,string[]>
     */
    public $OnlySerializeId = [];

    /**
     * A callback that returns the key to use when a nested object is replaced
     * with its identifier during serialization
     *
     * For example:
     *
     * ```php
     * $rules->GetSerializedIdKey = fn($key): string => "_{$key}_id";
     * ```
     *
     * The default is to append `_id` to the key being replaced, e.g. `user`
     * becomes `user_id`.
     *
     * @var callable|null
     * ```php
     * callback(string $key): string
     * ```
     */
    public $GetSerializedIdKey;

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
    public $OnlySerializePlaceholders = false;

    private $RuleCache = [];

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

        return $this->RuleCache[$cacheKey][$class] = $rules;
    }

    /**
     * @return string[]
     */
    public function getDoNotSerialize(string $class, ?string $untilClass = null): array
    {
        return $this->_mergeWithParentRules($class, $untilClass, $this->DoNotSerialize, __METHOD__);
    }

    /**
     * @return string[]
     */
    public function getOnlySerializeId(string $class, ?string $untilClass = null): array
    {
        return $this->_mergeWithParentRules($class, $untilClass, $this->OnlySerializeId, __METHOD__);
    }

    public function getSerializedIdKeyCallback(): Closure
    {
        if (is_null($this->GetSerializedIdKey))
        {
            return fn(string $key): string => $key . "_id";
        }
        elseif (!($this->GetSerializedIdKey instanceof Closure))
        {
            return Closure::fromCallable($this->GetSerializedIdKey);
        }

        return $this->GetSerializedIdKey;
    }

}
