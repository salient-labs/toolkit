<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Container\RequiresContainer;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Entity\Constructible;
use Salient\Contract\Core\Entity\Treeable;
use Salient\Core\Legacy\Introspector;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Generator;

/**
 * @api
 *
 * @phpstan-require-implements Constructible
 */
trait ConstructibleTrait
{
    use RequiresContainer;

    /**
     * @inheritDoc
     */
    public static function construct(
        array $data,
        ?object $parent = null,
        ?ContainerInterface $container = null
    ) {
        if ($parent && !$parent instanceof Treeable) {
            throw new InvalidArgumentTypeException(2, 'parent', Treeable::class, $parent);
        }

        $container = self::requireContainer($container);

        return Introspector::getService($container, static::class)
            ->getCreateFromClosure(true)($data, $container, null, $parent);
    }

    /**
     * @inheritDoc
     */
    public static function constructMultiple(
        iterable $data,
        int $conformity = Constructible::CONFORMITY_NONE,
        ?object $parent = null,
        ?ContainerInterface $container = null
    ): Generator {
        if ($parent && !$parent instanceof Treeable) {
            throw new InvalidArgumentTypeException(3, 'parent', Treeable::class, $parent);
        }

        $container = self::requireContainer($container);
        $introspector = Introspector::getService($container, static::class);

        foreach ($data as $key => $data) {
            /** @disregard P1012 */
            $closure ??= $conformity === self::CONFORMITY_PARTIAL || $conformity === self::CONFORMITY_COMPLETE
                ? $introspector->getCreateFromSignatureClosure(array_keys($data), true)
                : $introspector->getCreateFromClosure(true);

            yield $key => $closure($data, $container, null, $parent);
        }
    }
}
