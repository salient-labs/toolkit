<?php declare(strict_types=1);

namespace Salient\Core\Internal;

use Salient\Core\Introspector as IS;

/**
 * @internal
 */
trait ReadPropertyTrait
{
    /**
     * @inheritDoc
     */
    public function __get(string $name)
    {
        return $this->readProperty('get', $name);
    }

    /**
     * @inheritDoc
     */
    public function __isset(string $name): bool
    {
        return (bool) $this->readProperty('isset', $name);
    }

    /**
     * @return mixed
     */
    private function readProperty(string $action, string $name)
    {
        return IS::get(static::class)->getPropertyActionClosure($name, $action)($this);
    }
}