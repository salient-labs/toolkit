<?php declare(strict_types=1);

namespace Salient\Container\Exception;

use Salient\Contract\Container\Exception\ArgumentsNotUsedException as ArgumentsNotUsedExceptionInterface;
use Salient\Core\Exception\Exception;

/**
 * Thrown when a container cannot pass arguments to a service, e.g. because it
 * resolves to a shared instance
 */
class ArgumentsNotUsedException extends Exception implements ArgumentsNotUsedExceptionInterface {}
