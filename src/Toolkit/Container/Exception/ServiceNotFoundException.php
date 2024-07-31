<?php declare(strict_types=1);

namespace Salient\Container\Exception;

use Salient\Contract\Container\ServiceNotFoundExceptionInterface;
use Salient\Core\AbstractException;

/**
 * Thrown when a container cannot resolve a service
 *
 * @api
 */
class ServiceNotFoundException extends AbstractException implements ServiceNotFoundExceptionInterface {}
