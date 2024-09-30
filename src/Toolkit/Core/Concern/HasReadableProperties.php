<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Entity\Readable;
use Salient\Core\Introspector;

/**
 * Implements Readable
 *
 * - If `_get<Property>()` is defined, it is called instead of returning the
 *   value of `<Property>`.
 * - If `_isset<Property>()` is defined, it is called instead of returning
 *   `isset(<Property>)`.
 * - The existence of `_get<Property>()` makes `<Property>` readable, regardless
 *   of {@see Readable::getReadableProperties()}'s return value.
 *
 * @api
 *
 * @see Readable
 */
trait HasReadableProperties
{
    public static function getReadableProperties(): array
    {
        return [];
    }

    /**
     * @return mixed
     */
    private function getProperty(string $action, string $name)
    {
        return Introspector::get(static::class)->getPropertyActionClosure($name, $action)($this);
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->getProperty('get', $name);
    }

    public function __isset(string $name): bool
    {
        return (bool) $this->getProperty('isset', $name);
    }
}
