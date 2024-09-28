<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\Exception\MultipleErrorExceptionInterface;
use Salient\Core\Concern\MultipleErrorExceptionTrait;

/**
 * Base class for runtime exceptions that represent multiple errors
 *
 * @api
 */
abstract class AbstractMultipleErrorException extends AbstractException implements MultipleErrorExceptionInterface
{
    use MultipleErrorExceptionTrait;
}
