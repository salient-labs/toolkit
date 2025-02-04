<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Core\Exception\ExceptionInterface;
use RuntimeException;

/**
 * Base class for runtime exceptions
 *
 * @api
 */
abstract class Exception extends RuntimeException implements ExceptionInterface
{
    use ExceptionTrait;
}
