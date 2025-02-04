<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Core\Exception\ExceptionInterface;
use RuntimeException;

/**
 * @api
 */
abstract class Exception extends RuntimeException implements ExceptionInterface
{
    use ExceptionTrait;
}
