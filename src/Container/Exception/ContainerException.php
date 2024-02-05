<?php declare(strict_types=1);

namespace Lkrms\Container\Exception;

use Lkrms\Exception\Exception;
use Psr\Container\ContainerExceptionInterface;

/**
 * Base class for container exceptions
 *
 * @api
 */
abstract class ContainerException extends Exception implements ContainerExceptionInterface {}
