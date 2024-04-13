<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\ExceptionInterface;
use Salient\Core\Concern\ExceptionTrait;

/**
 * Base class for runtime exceptions
 *
 * @api
 */
abstract class AbstractException extends \RuntimeException implements ExceptionInterface
{
    use ExceptionTrait;
}
