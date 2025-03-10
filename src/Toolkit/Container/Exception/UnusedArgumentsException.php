<?php declare(strict_types=1);

namespace Salient\Container\Exception;

use Salient\Contract\Container\Exception\UnusedArgumentsException as UnusedArgumentsExceptionInterface;

/**
 * @internal
 */
class UnusedArgumentsException extends ContainerException implements UnusedArgumentsExceptionInterface {}
