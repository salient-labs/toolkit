<?php

declare(strict_types=1);

namespace Lkrms\Sync;

use JsonSerializable;
use Lkrms\Convert;
use Lkrms\Mixin\IExtensible;
use Lkrms\Mixin\IResolvable;
use Lkrms\Mixin\TConstructible;
use Lkrms\Mixin\TExtensible;

abstract class AbstractEntity implements IExtensible, IResolvable, JsonSerializable
{
    use TConstructible, TExtensible;

    /**
     * @var AbstractEntity
     */
    private static $SerializeRoot;

    /**
     * @var array<string,string[]>
     */
    private static $DoNotSerialize = [];

    public function Serialize(): array
    {
        return Convert::ObjectToArray($this);
    }

    private function _jsonSerialize(&$node)
    {
        if ($node instanceof AbstractEntity)
        {
            $node = $node->Serialize();
        }

        if (is_array($node))
        {
            foreach ($node as & $child)
            {
                if (is_null($child) || is_scalar($child))
                {
                    continue;
                }

                $this->_jsonSerialize($child);
            }
        }
        elseif (is_object($node))
        {
            $keys = array_keys(Convert::ObjectToArray($node));

            foreach ($keys as $key)
            {
                if (is_null($node->$key) || is_scalar($node->$key))
                {
                    continue;
                }

                $this->_jsonSerialize($node->key);
            }
        }
    }

    final public function jsonSerialize(): mixed
    {
        self::$SerializeRoot = $this;
        self::ClearDoNotSerialize();

        // Recurse into child nodes
        $json = $this;
        $this->_jsonSerialize($json);

        self::$SerializeRoot = null;

        return $json;
    }

    protected function GetSerializeRoot(): ?AbstractEntity
    {
        return self::$SerializeRoot;
    }

    protected static function SetDoNotSerialize(string $class, array $properties)
    {
        self::$DoNotSerialize[$class] = array_flip($properties);
    }

    protected static function GetDoNotSerialize(string $class): array
    {
        return self::$DoNotSerialize[$class] ?? [];
    }

    protected static function ClearDoNotSerialize()
    {
        self::$DoNotSerialize = [];
    }
}

