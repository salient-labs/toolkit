<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Container\RequiresContainer;
use Salient\Contract\Catalog\ListConformity;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Entity\Constructible;
use Salient\Contract\Core\Entity\Treeable;
use Salient\Core\Introspector;
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
        int $conformity = ListConformity::NONE,
        ?object $parent = null,
        ?ContainerInterface $container = null
    ): Generator {
        if ($parent && !$parent instanceof Treeable) {
            throw new InvalidArgumentTypeException(3, 'parent', Treeable::class, $parent);
        }

        $container = self::requireContainer($container);

        $closure = null;
        foreach ($data as $key => $array) {
            if (!$closure) {
                $builder = Introspector::getService($container, static::class);
                $closure =
                    in_array($conformity, [ListConformity::PARTIAL, ListConformity::COMPLETE])
                        ? $builder->getCreateFromSignatureClosure(array_keys($array), true)
                        : $builder->getCreateFromClosure(true);
            }

            yield $key => $closure($array, $container, null, $parent);
        }
    }
}
