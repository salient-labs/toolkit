<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Salient\Container\Contract\ServiceAwareInterface;
use Salient\Core\Catalog\ListConformity;
use Salient\Iterator\Contract\FluentIteratorInterface;

/**
 * Serviced by a provider
 *
 * @template TProvider of ProviderInterface
 * @template TContext of ProviderContextInterface
 *
 * @extends ProviderAwareInterface<TProvider>
 * @extends ProviderContextAwareInterface<TContext>
 */
interface Providable extends
    ProviderAwareInterface,
    ServiceAwareInterface,
    ProviderContextAwareInterface
{
    /**
     * Create an instance of the class from an array on behalf of a provider
     *
     * @param mixed[] $data
     * @param TProvider $provider
     * @param TContext|null $context
     * @return static
     */
    public static function provide(
        array $data,
        ProviderInterface $provider,
        ?ProviderContextInterface $context = null
    );

    /**
     * Create instances of the class from arrays on behalf of a provider
     *
     * @param iterable<array-key,mixed[]> $list
     * @param TProvider $provider
     * @param ListConformity::* $conformity
     * @param TContext|null $context
     * @return FluentIteratorInterface<array-key,static>
     */
    public static function provideList(
        iterable $list,
        ProviderInterface $provider,
        $conformity = ListConformity::NONE,
        ?ProviderContextInterface $context = null
    ): FluentIteratorInterface;

    /**
     * Called after data from the provider has been applied to the object
     */
    public function postLoad(): void;
}
