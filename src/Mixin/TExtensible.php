<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

/**
 * Used alongside IExtensible to implement arbitrary property storage
 *
 * @package Lkrms
 * @see IExtensible
 * @see TConstructible
 */
trait TExtensible
{
    private $MetaProperties = [];

    public function SetMetaProperty(string $name, $value): void
    {
        $this->MetaProperties[$name] = $value;
    }

    public function GetMetaProperty(string $name)
    {
        return $this->MetaProperties[$name] ?? null;
    }

    public function IsMetaPropertySet(string $name): bool
    {
        return isset($this->MetaProperties[$name]);
    }

    public function UnsetMetaProperty(string $name): void
    {
        unset($this->MetaProperties[$name]);
    }
}

