<?php declare(strict_types=1);

namespace Lkrms\Exception;

use Salient\Core\Utility\Get;

/**
 * Thrown when a function receives an argument that is not of the required type
 */
class InvalidArgumentTypeException extends InvalidArgumentException
{
    /**
     * @param int $position The argument number (1-based).
     * @param string $name The name of the argument.
     * @param string $type The expected type.
     * @param mixed $value The value given.
     */
    public function __construct(int $position, string $name, string $type, $value)
    {
        parent::__construct(sprintf(
            'Argument #%d ($%s) must be of type %s, %s given',
            $position,
            ltrim($name, '$'),
            $type,
            Get::type($value)
        ));
    }
}
