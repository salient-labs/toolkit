<?php declare(strict_types=1);

namespace Lkrms\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * Base class for container exceptions
 */
abstract class ContainerException extends \Lkrms\Exception\Exception implements ContainerExceptionInterface {}
