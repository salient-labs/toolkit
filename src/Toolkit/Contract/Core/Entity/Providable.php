<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

use Salient\Contract\Container\ServiceAwareInterface;
use Salient\Contract\Core\Exception\InvalidDataException;
use Salient\Contract\Core\Provider\ProviderAwareInterface;
use Salient\Contract\Core\Provider\ProviderContextAwareInterface;
use Salient\Contract\Core\Provider\ProviderContextInterface;
use Salient\Contract\Core\Provider\ProviderInterface;
use Salient\Contract\Core\ListConformity;

/**
 * @api
 *
 * @template TProvider of ProviderInterface
 * @template TContext of ProviderContextInterface
 *
 * @extends ProviderAwareInterface<TProvider>
 * @extends ProviderContextAwareInterface<TContext>
 */
interface Providable extends
    ProviderAwareInterface,
    ProviderContextAwareInterface,
    ServiceAwareInterface
{
    /**
     * Get an instance from an array on behalf of a provider
     *
     * Values in `$data` are applied as per {@see Constructible::construct()}.
     *
     * @param mixed[] $data
     * @param TContext $context
     * @return static
     * @throws InvalidDataException if values in `$data` do not satisfy the
     * constructor or cannot be applied to the class.
     */
    public static function provide(
        array $data,
        ProviderContextInterface $context
    );

    /**
     * Get instances from arrays on behalf of a provider
     *
     * Values in `$data` arrays are applied as per {@see provide()}.
     *
     * @template TKey of array-key
     *
     * @param iterable<TKey,mixed[]> $data
     * @param TContext $context
     * @param ListConformity::* $conformity
     * @return iterable<TKey,static>
     * @throws InvalidDataException if values in `$data` arrays do not satisfy
     * the constructor or cannot be applied to the class.
     */
    public static function provideMultiple(
        iterable $data,
        ProviderContextInterface $context,
        int $conformity = ListConformity::NONE
    ): iterable;

    /**
     * Called after provider data is applied to the object
     */
    public function postLoad(): void;
}
