<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

use Lkrms\Convert;

/**
 * Implements IExtensible and IResolvable to provide arbitrary property storage
 *
 * @package Lkrms
 * @see IExtensible
 * @see IResolvable
 * @see TConstructible
 */
trait TExtensible
{
    use TSettable;

    protected $MetaProperties = [];

    protected $MetaPropertyNames = [];

    protected function NormalisePropertyName(string $name, bool $save = false)
    {
        $normalised = Convert::IdentifierToSnakeCase($name);

        if ($save)
        {
            $this->MetaPropertyNames[$normalised] = $name;
        }

        return $normalised;
    }

    public function ResolvePropertyName(string $name): string
    {
        return $this->NormalisePropertyName($name);
    }

    public function SetMetaProperty(string $name, $value): void
    {
        $this->MetaProperties[$this->NormalisePropertyName($name, true)] = $value;
    }

    public function GetMetaProperty(string $name)
    {
        return $this->MetaProperties[$this->NormalisePropertyName($name)] ?? null;
    }

    public function IsMetaPropertySet(string $name): bool
    {
        return isset($this->MetaProperties[$this->NormalisePropertyName($name)]);
    }

    public function UnsetMetaProperty(string $name): void
    {
        unset($this->MetaProperties[$this->NormalisePropertyName($name)]);
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

