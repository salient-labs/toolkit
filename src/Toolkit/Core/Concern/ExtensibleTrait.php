<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Core\Contract\Extensible;
use Salient\Core\Introspector as IS;

/**
 * Implements Extensible to store arbitrary property values
 *
 * @see Extensible
 */
trait ExtensibleTrait
{
    /**
     * Normalised property name => value
     *
     * @var array<string,mixed>
     */
    private $MetaProperties = [];

    /**
     * Normalised property name => first name passed to setMetaProperty
     *
     * @var array<string,string>
     */
    private $MetaPropertyNames = [];

    final public function clearMetaProperties()
    {
        $this->MetaProperties = [];
        $this->MetaPropertyNames = [];

        return $this;
    }

    /**
     * @param mixed $value
     */
    final public function setMetaProperty(string $name, $value): void
    {
        $normalised = IS::get(static::class)->maybeNormalise($name);
        $this->MetaProperties[$normalised] = $value;
        if (!array_key_exists($normalised, $this->MetaPropertyNames)) {
            $this->MetaPropertyNames[$normalised] = $name;
        }
    }

    /**
     * @return mixed
     */
    final public function getMetaProperty(string $name)
    {
        return $this->MetaProperties[IS::get(static::class)->maybeNormalise($name)] ?? null;
    }

    final public function isMetaPropertySet(string $name): bool
    {
        return isset($this->MetaProperties[IS::get(static::class)->maybeNormalise($name)]);
    }

    final public function unsetMetaProperty(string $name): void
    {
        unset($this->MetaProperties[IS::get(static::class)->maybeNormalise($name)]);
    }

    final public function getMetaProperties(): array
    {
        return array_combine(
            array_map(
                fn(string $name): string => $this->MetaPropertyNames[$name],
                array_keys($this->MetaProperties)
            ),
            $this->MetaProperties
        );
    }
}
