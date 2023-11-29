<?php declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Exception\Concern\ExceptionTrait;
use Lkrms\Exception\Contract\ExceptionInterface;

/**
 * Base class for runtime exceptions
 */
abstract class Exception extends \RuntimeException implements ExceptionInterface
{
    use ExceptionTrait;
}
