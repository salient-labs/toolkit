<?php declare(strict_types=1);

namespace Salient\Core\Utility\Exception;

use Throwable;

/**
 * @internal
 */
abstract class AbstractUtilityException extends \RuntimeException
{
    /**
     * @codeCoverageIgnore
     */
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
