<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

use Lkrms\Convert;

/**
 * Used alongside IExtensible to implement arbitrary property storage
 *
 * @package Lkrms
 * @see IExtensible
 * @see TConstructible
 */
trait TExtensible
{
    use TGettable, TSettable;

    protected $MetaProperties = [];

    protected $MetaPropertyNames = [];

    protected function NormaliseMetaProperty(string $name)
    {
        $normalised = Convert::IdentifierToSnakeCase($name);
        $this->MetaPropertyNames[$normalised] = $this->MetaPropertyNames[$normalised] ?? $name;

        return $normalised;
    }

    public function SetMetaProperty(string $name, $value): void
    {
        $this->MetaProperties[$this->NormaliseMetaProperty($name)] = $value;
    }

    public function GetMetaProperty(string $name)
    {
        return $this->MetaProperties[$this->NormaliseMetaProperty($name)] ?? null;
    }

    public function IsMetaPropertySet(string $name): bool
    {
        return isset($this->MetaProperties[$this->NormaliseMetaProperty($name)]);
    }

    public function UnsetMetaProperty(string $name): void
    {
        unset($this->MetaProperties[$this->NormaliseMetaProperty($name)]);
    }

    public function GetMetaProperties(): array
    {
        $names = array_map(
            function ($name) { return $this->MetaPropertyNames[$name]; },
            array_keys($this->MetaProperties)
        );

        return array_combine($names, $this->MetaProperties);
    }
}

