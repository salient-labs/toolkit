<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Support\Introspector as IS;

/**
 * Implements IExtensible to store arbitrary property values
 *
 * @see \Lkrms\Contract\IExtensible
 */
trait TExtensible
{
    /**
     * Normalised property name => value
     *
     * @var array<string,mixed>
     */
    private $_MetaProperties = [];

    /**
     * Normalised property name => first name passed to setMetaProperty
     *
     * @var array<string,string>
     */
    private $_MetaPropertyNames = [];

    final public function clearMetaProperties()
    {
        $this->_MetaProperties = [];
        $this->_MetaPropertyNames = [];

        return $this;
    }

    /**
     * @param mixed $value
     */
    final public function setMetaProperty(string $name, $value): void
    {
        $normalised = IS::get(static::class)->maybeNormalise($name);
        $this->_MetaProperties[$normalised] = $value;
        if (!array_key_exists($normalised, $this->_MetaPropertyNames)) {
            $this->_MetaPropertyNames[$normalised] = $name;
        }
    }

    /**
     * @return mixed
     */
    final public function getMetaProperty(string $name)
    {
        return $this->_MetaProperties[IS::get(static::class)->maybeNormalise($name)] ?? null;
    }

    final public function isMetaPropertySet(string $name): bool
    {
        return isset($this->_MetaProperties[IS::get(static::class)->maybeNormalise($name)]);
    }

    final public function unsetMetaProperty(string $name): void
    {
        unset($this->_MetaProperties[IS::get(static::class)->maybeNormalise($name)]);
    }

    final public function getMetaProperties(): array
    {
        return array_combine(
            array_map(
                fn(string $name): string => $this->_MetaPropertyNames[$name],
                array_keys($this->_MetaProperties)
            ),
            $this->_MetaProperties
        );
    }
}
