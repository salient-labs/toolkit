<?php declare(strict_types=1);

namespace Salient\Contract\Container\Exception;

use Psr\Container\ContainerExceptionInterface as PsrContainerExceptionInterface;
use Throwable;

/**
 * @api
 */
interface ContainerException extends PsrContainerExceptionInterface, Throwable {}
