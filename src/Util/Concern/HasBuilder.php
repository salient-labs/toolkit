<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Concept\Builder;
use Lkrms\Contract\Buildable;
use Salient\Container\ContainerInterface;

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

    final public static function build(?ContainerInterface $container = null): Builder
    {
        return static::getBuilder()::build($container);
    }

    final public static function resolve($object)
    {
        return static::getBuilder()::resolve($object);
    }
}
