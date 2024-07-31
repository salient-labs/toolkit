<?php declare(strict_types=1);

namespace Salient\Contract\Container;

use Psr\Container\NotFoundExceptionInterface;

/** @api */
interface ServiceNotFoundExceptionInterface extends ContainerExceptionInterface, NotFoundExceptionInterface {}
