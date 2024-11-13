<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Buildable;
use Salient\Contract\Core\BuilderInterface;

/**
 * Implements Buildable
 *
 * @see Buildable
 *
 * @api
 *
 * @template TBuilder of BuilderInterface
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
        return self::class . 'Builder';
    }

    /**
     * @inheritDoc
     */
    public static function build(?ContainerInterface $container = null): BuilderInterface
    {
        return static::getBuilder()::create($container);
    }

    /**
     * @inheritDoc
     */
    public static function resolve($object)
    {
        /** @var static */
        return static::getBuilder()::resolve($object);
    }
}
