<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Core\Exception\MultipleErrorExceptionInterface;

/**
 * Base class for runtime exceptions that represent multiple errors
 *
 * @api
 */
abstract class MultipleErrorException extends Exception implements MultipleErrorExceptionInterface
{
    use MultipleErrorExceptionTrait;
}
