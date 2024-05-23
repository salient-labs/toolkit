<?php declare(strict_types=1);

namespace Salient\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when a container cannot resolve a service
 *
 * @api
 */
class ContainerServiceNotFoundException extends AbstractContainerException implements NotFoundExceptionInterface {}
