<?php declare(strict_types=1);

namespace Salient\Contract\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * @api
 */
interface ServiceNotFoundException extends ContainerException, NotFoundExceptionInterface {}
