<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Buildable;
use Salient\Contract\Core\BuilderInterface;

/**
 * @api
 *
 * @template TBuilder of BuilderInterface
 *
 * @phpstan-require-implements Buildable<TBuilder>
 */
trait HasBuilder
{
    /**
     * Get the name of a builder for the class
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
    public static function build(): BuilderInterface
    {
        return static::getBuilder()::create();
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
