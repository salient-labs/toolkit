<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Entity\Extensible;
use Salient\Core\Internal\ReadPropertyTrait;
use Salient\Core\Internal\WritePropertyTrait;
use Salient\Utility\Arr;

/**
 * Implements Extensible to store arbitrary property values
 *
 * @see Extensible
 *
 * @phpstan-require-implements Extensible
 */
trait ExtensibleTrait
{
    use ReadPropertyTrait;
    use WritePropertyTrait;

    /**
     * Normalised property name => value
     *
     * @var array<string,mixed>
     */
    protected $MetaProperties = [];

    /**
     * Normalised property name => first name passed to __set
     *
     * @var array<string,string>
     */
    protected $MetaPropertyNames = [];

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

    final public function getDynamicProperties(): array
    {
        return Arr::combine(
            array_map(
                fn(string $name): string => $this->MetaPropertyNames[$name],
                array_keys($this->MetaProperties)
            ),
            $this->MetaProperties
        );
    }
}
