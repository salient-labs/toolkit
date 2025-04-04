<?php declare(strict_types=1);

namespace Salient\Contract\Container\Exception;

use Psr\Container\NotFoundExceptionInterface as PsrNotFoundExceptionInterface;

/**
 * @api
 */
interface ServiceNotFoundException extends ContainerException, PsrNotFoundExceptionInterface {}
