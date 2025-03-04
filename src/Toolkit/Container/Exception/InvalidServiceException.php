<?php declare(strict_types=1);

namespace Salient\Container\Exception;

use Salient\Contract\Container\Exception\InvalidServiceExceptionInterface;
use Salient\Core\Exception\Exception;

/**
 * Thrown when a service cannot be bound to a container
 */
class InvalidServiceException extends Exception implements InvalidServiceExceptionInterface {}
