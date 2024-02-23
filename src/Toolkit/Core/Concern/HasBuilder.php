<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Container\ContainerInterface;
use Salient\Core\Contract\Buildable;
use Salient\Core\AbstractBuilder;

/**
 * Implements Buildable
 *
 * @see Buildable
 */
trait HasBuilder
{
    public static function getBuilder(): string
    {
        return static::class . 'Builder';
    }

    final public static function build(?ContainerInterface $container = null): AbstractBuilder
    {
        return static::getBuilder()::build($container);
    }

    final public static function resolve($object)
    {
        return static::getBuilder()::resolve($object);
    }
}
