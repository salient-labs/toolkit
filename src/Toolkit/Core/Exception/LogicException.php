<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Core\ExceptionInterface;
use Salient\Core\Concern\ExceptionTrait;

/**
 * @api
 */
class LogicException extends \LogicException implements ExceptionInterface
{
    use ExceptionTrait;
}
