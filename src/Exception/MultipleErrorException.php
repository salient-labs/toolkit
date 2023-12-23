<?php declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Exception\Concern\MultipleErrorExceptionTrait;
use Lkrms\Exception\Contract\MultipleErrorExceptionInterface;

/**
 * Base class for exceptions that represent multiple errors
 */
abstract class MultipleErrorException extends Exception implements MultipleErrorExceptionInterface
{
    use MultipleErrorExceptionTrait;
}
