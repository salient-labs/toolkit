<?php declare(strict_types=1);

namespace Salient\Tests\Container;

use Salient\Container\ContainerInterface;

/**
 * @template T of ContainerInterface
 *
 * @extends A<T>
 */
class B extends A {}
