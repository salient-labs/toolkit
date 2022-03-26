<?php

declare(strict_types=1);

namespace Lkrms\Sync;

use JsonSerializable;
use Lkrms\Convert;
use Lkrms\Template\IAccessible;
use Lkrms\Template\IExtensible;
use Lkrms\Template\IGettable;
use Lkrms\Template\IResolvable;
use Lkrms\Template\ISettable;
use Lkrms\Template\TConstructible;
use Lkrms\Template\TExtensible;
use UnexpectedValueException;

/**
 * Represents the state of an entity in an external system
 *
 * @package Lkrms
 */
abstract class SyncEntity implements IGettable, ISettable, IResolvable, IExtensible, JsonSerializable
{
    use TConstructible, TExtensible;

    /**
     * @var int|string
     */
    public $Id;

    /**
     * @var array<string,array<string,int>>
     */
    private $DoNotSerialize;

    /**
     * @var array<string,string[]>
     */
    private $OnlySerializeId;

    public function getSettable(): ?array
    {
        return IAccessible::ALLOW_ALL_PROTECTED;
    }

    protected function serialize(): array
    {
        return Convert::objectToArray($this);
    }

    /**
     * Specify keys to remove from the serialized forms of nested entity classes
     *
     * `DoNotSerialize` rules are only applied by the entity being serialized
     * via one of its public methods, e.g. {@see SyncEntity::jsonSerialize()}.
     * Rules for entities nested within the entity being serialized are not
     * considered.
     *
     * For example, a `User` entity that returns an `OrgUnit` entity to
     * serialize could prevent recursion by deleting the `users` key from
     * serialized `OrgUnit` entities as follows:
     *
     * ```php
     * protected function getDoNotSerialize(): ?array
     * {
     *     return [
     *         OrgUnit::class => [
     *             "users",
     *         ],
     *     ];
     * }
     * ```
     *
     * @return null|array
     * @see SyncEntity::getOnlySerializeId()
     * @see SyncEntity::serialize()
     */
    protected function getDoNotSerialize(): ?array
    {
        return null;
    }

    /**
     * Specify keys to replace with identifier keys in the serialized forms of
     * nested entity classes
     *
     * `OnlySerializeId` rules are only applied by the entity being serialized
     * via one of its public methods, e.g. {@see SyncEntity::jsonSerialize()}.
     * Rules for entities nested within the entity being serialized are not
     * considered.
     *
     * For example, an `OrgUnit` entity that returns a list of `User` entities
     * to serialize could prevent recursion by replacing the `org_unit` key in
     * serialized `User` entities with an `org_unit_id` key as follows:
     *
     * ```php
     * protected function getOnlySerializeId(): ?array
     * {
     *     return [
     *         User::class => [
     *             "org_unit",
     *         ],
     *     ];
     * }
     * ```
     *
     * @return null|array
     * @see SyncEntity::getSerializedIdKey()
     * @see SyncEntity::getDoNotSerialize()
     * @see SyncEntity::serialize()
     */
    protected function getOnlySerializeId(): ?array
    {
        return null;
    }

    protected function getSerializedIdKey(string $key): string
    {
        return $key . "_id";
    }

    private function getInstanceKey(): string
    {
        return static::class . "::{$this->Id}";
    }

    private function _serializeId(
        &$node,
        SyncEntity $parentEntity,
        array & $parentArray,
        string $parentKey
    )
    {
        // Rename $node to `<parent_key>_id` or similar if:
        // - its parent was a SyncEntity ($parentEntity)
        // - $parentEntity->getSerializedIdKey($parentKey) returns a new name
        // - the new name hasn't already been used in $parentArray
        if (!is_null($parentEntity) &&
            $newParentKey = $parentEntity->getSerializedIdKey($parentKey))
        {
            if (array_key_exists($newParentKey, $parentArray))
            {
                throw new UnexpectedValueException("Array key '$newParentKey' already exists");
            }

            $parentArray[$newParentKey] = $node->Id;
            unset($parentArray[$parentKey]);
        }

        $node = $node->Id;
    }

    private function _serialize(
        &$node,
        SyncEntity $root,
        $parents  = [],
        $siblings = [],
        SyncEntity $parentEntity = null,
        array & $parentArray     = null,
        string $parentKey        = null
    )
    {
        $entityNode = null;

        if ($node instanceof SyncEntity)
        {
            $entityNode = $node;

            // Prevent recursion by replacing each $node descended from itself
            // or a previous sibling of itself with $node->Id
            if ($parents[$node->getInstanceKey()] ?? false)
            {
                $this->_serializeId($node, $parentEntity, $parentArray, $parentKey);
            }
            else
            {
                $onlySerializeId = $root->OnlySerializeId[get_class($node)] ?? [];

                if ($noSerialize = $root->DoNotSerialize[get_class($node)] ?? null)
                {
                    $node = array_diff_key($node->serialize(), $noSerialize);
                }
                else
                {
                    $node = $node->serialize();
                }

                foreach ($onlySerializeId as $key)
                {
                    $this->_serializeId($node[$key], $entityNode, $node, $key);
                }
            }
        }

        $parents  = array_merge($parents, $siblings);
        $siblings = [];

        if (is_array($node))
        {
            foreach ($node as $child)
            {
                if ($child instanceof SyncEntity)
                {
                    $siblings[$child->getInstanceKey()] = true;
                }
            }

            foreach ($node as $key => & $child)
            {
                if (is_null($child) || is_scalar($child))
                {
                    continue;
                }

                $this->_serialize($child, $root, $parents, $siblings, $entityNode, $node, $key);
            }
        }
        elseif (is_object($node))
        {
            $keys = array_keys(Convert::objectToArray($node));

            foreach ($keys as $key)
            {
                if ($node->$key instanceof SyncEntity)
                {
                    $siblings[$node->$key->getKey()] = true;
                }
            }

            foreach ($keys as $key)
            {
                if (is_null($node->$key) || is_scalar($node->$key))
                {
                    continue;
                }

                $this->_serialize($node->$key, $root, $parents, $siblings);
            }
        }
    }

    public function toArray(): array
    {
        $this->DoNotSerialize  = array_map('array_flip', $this->getDoNotSerialize() ?: []);
        $this->OnlySerializeId = $this->getOnlySerializeId() ?: [];

        $array = $this;
        $this->_serialize($array, $this);

        return (array)$array;
    }

    final public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

