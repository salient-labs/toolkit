<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Core\ExceptionInterface;
use Salient\Core\Concern\ExceptionTrait;

/**
 * @api
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
    use ExceptionTrait;
}
