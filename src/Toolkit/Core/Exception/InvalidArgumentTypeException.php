<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Core\Utility\Get;

/**
 * @api
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
            Get::type($value),
        ));
    }
}
