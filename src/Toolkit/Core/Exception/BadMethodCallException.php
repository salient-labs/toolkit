<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Core\ExceptionInterface;
use Salient\Core\Concern\ExceptionTrait;

/**
 * @api
 */
class BadMethodCallException extends \BadMethodCallException implements ExceptionInterface
{
    use ExceptionTrait;
}
