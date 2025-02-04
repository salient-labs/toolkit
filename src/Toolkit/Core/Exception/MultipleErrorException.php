<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Core\Exception\MultipleErrorExceptionInterface;

/**
 * @api
 */
abstract class MultipleErrorException extends Exception implements MultipleErrorExceptionInterface
{
    use MultipleErrorExceptionTrait;
}
