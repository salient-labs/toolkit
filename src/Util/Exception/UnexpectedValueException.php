<?php declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Exception\Concern\ExceptionTrait;
use Lkrms\Exception\Contract\ExceptionInterface;

/**
 * Thrown when a value is missing or invalid
 */
class UnexpectedValueException extends \UnexpectedValueException implements ExceptionInterface
{
    use ExceptionTrait;
}
