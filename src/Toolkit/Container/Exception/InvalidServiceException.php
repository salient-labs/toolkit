<?php declare(strict_types=1);

namespace Salient\Container\Exception;

use Salient\Contract\Container\Exception\InvalidServiceException as InvalidServiceExceptionInterface;

/**
 * @internal
 */
class InvalidServiceException extends ContainerException implements InvalidServiceExceptionInterface {}
