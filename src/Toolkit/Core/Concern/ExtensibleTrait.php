<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Entity\Extensible;
use Salient\Core\Internal\ReadPropertyTrait;
use Salient\Core\Internal\WritePropertyTrait;

/**
 * @api
 *
 * @phpstan-require-implements Extensible
 */
trait ExtensibleTrait
{
    use ReadPropertyTrait;
    use WritePropertyTrait;

    /**
     * Normalised name => value
     *
     * @internal
     *
     * @var array<string,mixed>
     */
    protected $MetaProperties = [];

    /**
     * Normalised name => first name passed to __set
     *
     * @internal
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
    public function setDynamicProperties(array $values): void
    {
        $this->MetaProperties = [];
        $this->MetaPropertyNames = [];

        foreach ($values as $name => $value) {
            $this->__set($name, $value);
        }
    }

    /**
     * @inheritDoc
     */
    public function getDynamicProperties(): array
    {
        foreach ($this->MetaProperties as $name => $value) {
            $properties[$this->MetaPropertyNames[$name]] = $value;
        }
        return $properties ?? [];
    }
}
