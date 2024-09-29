<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Container\RequiresContainer;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Entity\Constructible;
use Salient\Contract\Core\ListConformity;
use Salient\Core\Introspector;
use Generator;

/**
 * Implements Constructible
 *
 * @see Constructible
 *
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
    final public static function construct(
        array $data,
        ?ContainerInterface $container = null,
        $parent = null
    ) {
        $container = self::requireContainer($container);

        return Introspector::getService($container, static::class)
            ->getCreateFromClosure(true)($data, $container, null, $parent);
    }

    /**
     * @inheritDoc
     */
    final public static function constructList(
        iterable $list,
        $conformity = ListConformity::NONE,
        ?ContainerInterface $container = null,
        $parent = null
    ): Generator {
        $container = self::requireContainer($container);

        $closure = null;
        foreach ($list as $key => $data) {
            if (!$closure) {
                $builder = Introspector::getService($container, static::class);
                $closure =
                    in_array($conformity, [ListConformity::PARTIAL, ListConformity::COMPLETE])
                        ? $builder->getCreateFromSignatureClosure(array_keys($data), true)
                        : $builder->getCreateFromClosure(true);
            }

            yield $key => $closure($data, $container, null, $parent);
        }
    }
}
