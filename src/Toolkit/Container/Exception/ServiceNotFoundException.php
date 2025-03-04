<?php declare(strict_types=1);

namespace Salient\Container\Exception;

use Salient\Contract\Container\Exception\ServiceNotFoundException as ServiceNotFoundExceptionInterface;
use Salient\Core\Exception\Exception;

/**
 * Thrown when a container cannot resolve a service
 *
 * @api
 */
class ServiceNotFoundException extends Exception implements ServiceNotFoundExceptionInterface {}
