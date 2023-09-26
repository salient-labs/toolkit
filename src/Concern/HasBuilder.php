<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Concept\Builder;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\ProvidesBuilder;

/**
 * Implements ProvidesBuilder
 *
 * @see ProvidesBuilder
 */
trait HasBuilder
{
    abstract public static function getBuilder(): string;

    final public static function build(?IContainer $container = null): Builder
    {
        if (!$container) {
            $container = Container::getGlobalContainer();
        }
        return $container->get(static::getBuilder());
    }

    final public static function resolve($object)
    {
        return static::getBuilder()::resolve($object);
    }
}
