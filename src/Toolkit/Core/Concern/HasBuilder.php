<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Buildable;
use Salient\Core\AbstractBuilder;

/**
 * Implements Buildable
 *
 * @see Buildable
 *
 * @api
 *
 * @template TBuilder of AbstractBuilder
 *
 * @phpstan-require-implements Buildable<TBuilder>
 */
trait HasBuilder
{
    /**
     * Get the name of a builder that creates instances of the class
     *
     * @return class-string<TBuilder>
     */
    protected static function getBuilder(): string
    {
        /** @var class-string<TBuilder> */
        return static::class . 'Builder';
    }

    /**
     * @inheritDoc
     */
    final public static function build(?ContainerInterface $container = null): AbstractBuilder
    {
        return static::getBuilder()::create($container);
    }

    /**
     * @inheritDoc
     */
    final public static function resolve($object)
    {
        /** @var static */
        return static::getBuilder()::resolve($object);
    }
}
