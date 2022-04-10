<?php

declare(strict_types=1);

namespace Lkrms\Template;

use Lkrms\Convert;

/**
 * Implements IExtensible to provide arbitrary property storage via normalised
 * property names
 *
 * @package Lkrms
 * @see IGettable
 * @see ISettable
 * @see IResolvable
 * @see IExtensible
 */
trait TExtensible
{
    use TGettable, TSettable;

    protected $MetaProperties = [];

    protected $MetaPropertyNames = [];

    public static function normalisePropertyName(string $name): string
    {
        return Convert::toSnakeCase($name);
    }

    public function setMetaProperty(string $name, $value): void
    {
        $normalised = self::normalisePropertyName($name);
        $this->MetaProperties[$normalised]    = $value;
        $this->MetaPropertyNames[$normalised] = $name;
    }

    public function getMetaProperty(string $name)
    {
        return $this->MetaProperties[self::normalisePropertyName($name)] ?? null;
    }

    public function isMetaPropertySet(string $name): bool
    {
        return isset($this->MetaProperties[self::normalisePropertyName($name)]);
    }

    public function unsetMetaProperty(string $name): void
    {
        unset($this->MetaProperties[self::normalisePropertyName($name)]);
    }

    public function getMetaProperties(): array
    {
        $names = array_map(
            function ($name) { return $this->MetaPropertyNames[$name]; },
            array_keys($this->MetaProperties)
        );

        return array_combine($names, $this->MetaProperties);
    }
}

