<?php declare(strict_types=1);

namespace Lkrms\Container\Exception;

/**
 * Thrown when a container cannot pass arguments to a service, e.g. because it
 * resolves to a shared instance
 */
class ContainerUnusableArgumentsException extends ContainerException {}
