<?php declare(strict_types=1);

namespace Lkrms\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when a container cannot resolve a service
 *
 * @api
 */
class ContainerServiceNotFoundException extends ContainerException implements NotFoundExceptionInterface {}
