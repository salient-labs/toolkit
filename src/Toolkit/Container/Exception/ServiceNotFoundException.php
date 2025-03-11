<?php declare(strict_types=1);

namespace Salient\Container\Exception;

use Salient\Contract\Container\Exception\ServiceNotFoundException as ServiceNotFoundExceptionInterface;

/**
 * @internal
 */
class ServiceNotFoundException extends ContainerException implements ServiceNotFoundExceptionInterface {}
