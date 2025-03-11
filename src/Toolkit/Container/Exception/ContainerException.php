<?php declare(strict_types=1);

namespace Salient\Container\Exception;

use Salient\Contract\Container\Exception\ContainerException as ContainerExceptionInterface;
use Salient\Core\Exception\Exception;

/**
 * @internal
 */
class ContainerException extends Exception implements ContainerExceptionInterface {}
