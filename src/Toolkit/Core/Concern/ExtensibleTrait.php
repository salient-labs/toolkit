<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Entity\Extensible;
use Salient\Core\Introspector as IS;

/**
 * Implements Extensible to store arbitrary property values
 *
 * @see Extensible
 *
 * @phpstan-require-implements Extensible
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
     * Normalised property name => first name passed to __set
     *
     * @var array<string,string>
     */
    private $MetaPropertyNames = [];

    /**
     * @inheritDoc
     */
    public static function getDynamicPropertiesProperty(): string
    {
        return 'MetaProperties';
    }

    /**
     * @inheritDoc
     */
    public static function getDynamicPropertyNamesProperty(): string
    {
        return 'MetaPropertyNames';
    }

    /**
     * @inheritDoc
     */
    final public function setDynamicProperties(array $values): void
    {
        $this->MetaProperties = [];
        $this->MetaPropertyNames = [];

        foreach ($values as $name => $value) {
            $this->__set($name, $value);
        }
    }

    /**
     * @param mixed $value
     */
    final public function __set(string $name, $value): void
    {
        if (($is = IS::get(static::class))->hasProperty($name)) {
            $is->getPropertyActionClosure($name, 'set')($this, $value);
            return;
        }
        $normalised = $is->maybeNormalise($name);
        $this->MetaProperties[$normalised] = $value;
        if (!array_key_exists($normalised, $this->MetaPropertyNames)) {
            $this->MetaPropertyNames[$normalised] = $name;
        }
    }

    /**
     * @return mixed
     */
    final public function __get(string $name)
    {
        if (($is = IS::get(static::class))->hasProperty($name)) {
            return $is->getPropertyActionClosure($name, 'get')($this);
        }
        return $this->MetaProperties[$is->maybeNormalise($name)] ?? null;
    }

    final public function __isset(string $name): bool
    {
        if (($is = IS::get(static::class))->hasProperty($name)) {
            return $is->getPropertyActionClosure($name, 'isset')($this);
        }
        return isset($this->MetaProperties[$is->maybeNormalise($name)]);
    }

    final public function __unset(string $name): void
    {
        if (($is = IS::get(static::class))->hasProperty($name)) {
            $is->getPropertyActionClosure($name, 'unset')($this);
            return;
        }
        $normalised = $is->maybeNormalise($name);
        unset(
            $this->MetaProperties[$normalised],
            $this->MetaPropertyNames[$normalised],
        );
    }

    final public function getDynamicProperties(): array
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
