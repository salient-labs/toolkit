<?php declare(strict_types=1);

namespace Lkrms\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when a service container cannot resolve a service to an object
 */
class ContainerServiceNotFoundException extends ContainerException implements NotFoundExceptionInterface {}
