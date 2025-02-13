<?php declare(strict_types=1);

namespace Salient\Utility\Exception;

use RuntimeException;
use Throwable;

/**
 * @internal
 */
class UtilityException extends RuntimeException
{
    /**
     * @api
     *
     * @codeCoverageIgnore
     */
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
