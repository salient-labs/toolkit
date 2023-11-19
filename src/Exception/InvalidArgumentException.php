<?php declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Exception\Concern\ExceptionTrait;
use Lkrms\Exception\Contract\ExceptionInterface;

/**
 * Thrown when an invalid argument is passed to a method
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
    use ExceptionTrait;
}
