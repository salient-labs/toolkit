<?php declare(strict_types=1);

namespace Salient\Utility\Exception;

use Salient\Utility\Get;
use InvalidArgumentException;

/**
 * @api
 */
class InvalidArgumentTypeException extends InvalidArgumentException
{
    /**
     * @api
     *
     * @param int<1,max> $number Argument number (1-based).
     * @param string $name Argument name (leading `'$'` removed if present).
     * @param string $type Expected type, e.g. `'string[]|null'`.
     * @param mixed $value Value given.
     */
    public function __construct(int $number, string $name, string $type, $value)
    {
        parent::__construct(sprintf(
            'Argument #%d ($%s) must be of type %s, %s given',
            $number,
            ltrim($name, '$'),
            $type,
            Get::type($value),
        ));
    }
}
