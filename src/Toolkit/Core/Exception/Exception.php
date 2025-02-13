<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Core\Exception\Exception as ExceptionInterface;
use RuntimeException;

/**
 * @api
 */
class Exception extends RuntimeException implements ExceptionInterface
{
    use ExceptionTrait;
}
