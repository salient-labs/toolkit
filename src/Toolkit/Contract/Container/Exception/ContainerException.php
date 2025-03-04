<?php declare(strict_types=1);

namespace Salient\Contract\Container\Exception;

use Psr\Container\ContainerExceptionInterface;
use Throwable;

/**
 * @api
 */
interface ContainerException extends ContainerExceptionInterface, Throwable {}
