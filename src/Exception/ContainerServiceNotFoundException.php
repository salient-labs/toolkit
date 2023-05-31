<?php declare(strict_types=1);

namespace Lkrms\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when a service container cannot resolve a class or service interface
 * to an instance
 *
 */
class ContainerServiceNotFoundException extends \Lkrms\Exception\Exception implements NotFoundExceptionInterface
{
}
