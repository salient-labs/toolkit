<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use ArrayAccess;
use ReturnTypeWillChange;

/**
 * Provides a standard interface to an underlying object or array and its values
 *
 * @api
 *
 * @extends ArrayAccess<array-key,mixed>
 */
interface GraphInterface extends ArrayAccess
{
    /**
     * @param mixed[]|object $value
     */
    public function __construct(&$value = []);

    /**
     * Get the underlying object or array
     *
     * @return mixed[]|object
     */
    public function getValue();

    /**
     * Get the properties or keys traversed to reach the current value
     *
     * @return array<array-key>
     */
    public function getPath(): array;

    /**
     * Get the value at the given offset
     *
     * If the value is an object or array, a new instance of the class is
     * returned to service it, otherwise the value itself is returned.
     *
     * @return static|resource|int|float|string|bool|null
     * @disregard P1038
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset);
}
