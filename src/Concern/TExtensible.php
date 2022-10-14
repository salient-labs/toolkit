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
    /**
     * Normalised property names => values
     *
     * @var array<string,mixed>
     */
    protected $MetaProperties = [];

    /**
     * Normalised property names => last names passed to setMetaProperty
     *
     * @var array<string,string>
     */
    protected $MetaPropertyNames = [];

    /**
     * Property names => normalised property names
     *
     * @var array<string,string>
     */
    private $MetaPropertyMap = [];

    final public function clearMetaProperties(): void
    {
        $this->MetaProperties    = [];
        $this->MetaPropertyNames = [];
        $this->MetaPropertyMap   = [];
    }

    private function normaliseMetaProperty(string $name): string
    {
        if (is_null($normalised = $this->MetaPropertyMap[$name] ?? null))
        {
            $normalised = ClosureBuilder::get(static::class)->maybeNormalise($name);
            $this->MetaPropertyMap[$name] = $normalised;
        }

        return $normalised;
    }

    final public function setMetaProperty(string $name, $value): void
    {
        $normalised = $this->normaliseMetaProperty($name);
        $this->MetaProperties[$normalised]    = $value;
        $this->MetaPropertyNames[$normalised] = $name;
    }

    final public function getMetaProperty(string $name)
    {
        return $this->MetaProperties[$this->normaliseMetaProperty($name)] ?? null;
    }

    final public function isMetaPropertySet(string $name): bool
    {
        return isset($this->MetaProperties[$this->normaliseMetaProperty($name)]);
    }

    final public function unsetMetaProperty(string $name): void
    {
        unset($this->MetaProperties[$this->normaliseMetaProperty($name)]);
    }

    final public function getMetaProperties(): array
    {
        $names = array_map(
            function ($name) { return $this->MetaPropertyNames[$name]; },
            array_keys($this->MetaProperties)
        );

        return array_combine($names, $this->MetaProperties);
    }
}
