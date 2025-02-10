<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Core\Exception\MultipleErrorException as MultipleErrorExceptionInterface;

/**
 * @api
 */
class MultipleErrorException extends Exception implements MultipleErrorExceptionInterface
{
    use MultipleErrorExceptionTrait;
}
