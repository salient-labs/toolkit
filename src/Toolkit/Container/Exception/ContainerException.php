<?php declare(strict_types=1);

namespace Salient\Container\Exception;

use Psr\Container\ContainerExceptionInterface;
use Salient\Core\AbstractException;

/**
 * Base class for container exceptions
 *
 * @api
 */
abstract class ContainerException extends AbstractException implements ContainerExceptionInterface {}
