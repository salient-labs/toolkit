<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Core\Concern\ExceptionTrait;
use Salient\Core\Contract\ExceptionInterface;
use RuntimeException;

/**
 * Base class for runtime exceptions
 */
abstract class AbstractException extends RuntimeException implements ExceptionInterface
{
    use ExceptionTrait;
}
