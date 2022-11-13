<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Support\ClosureBuilder;

/**
 * Implements IExtensible to store arbitrary property values
 *
 * @see \Lkrms\Contract\IExtensible
 */
trait TExtensible
{
    use HasIntrospector;

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
        $this->_MetaProperties    = [];
        $this->_MetaPropertyNames = [];

        return $this;
    }

    final public function setMetaProperty(string $name, $value): void
    {
        $normalised = $this->introspector()->maybeNormalise($name);
        $this->_MetaProperties[$normalised] = $value;
        if (!array_key_exists($normalised, $this->_MetaPropertyNames))
        {
            $this->_MetaPropertyNames[$normalised] = $name;
        }
    }

    final public function getMetaProperty(string $name)
    {
        return $this->_MetaProperties[$this->introspector()->maybeNormalise($name)] ?? null;
    }

    final public function isMetaPropertySet(string $name): bool
    {
        return isset($this->_MetaProperties[$this->introspector()->maybeNormalise($name)]);
    }

    final public function unsetMetaProperty(string $name): void
    {
        unset($this->_MetaProperties[$this->introspector()->maybeNormalise($name)]);
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
