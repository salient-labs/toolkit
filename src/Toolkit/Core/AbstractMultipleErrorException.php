<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Core\Concern\MultipleErrorExceptionTrait;
use Salient\Core\Contract\MultipleErrorExceptionInterface;

/**
 * Base class for runtime exceptions that represent multiple errors
 */
abstract class AbstractMultipleErrorException extends AbstractException implements MultipleErrorExceptionInterface
{
    use MultipleErrorExceptionTrait;
}
