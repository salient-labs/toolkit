<?php declare(strict_types=1);

namespace Salient\Container\Exception;

use Salient\Contract\Container\InvalidServiceExceptionInterface;
use Salient\Core\AbstractException;

/**
 * Thrown when a service cannot be bound to a container
 */
class InvalidServiceException extends AbstractException implements InvalidServiceExceptionInterface {}
