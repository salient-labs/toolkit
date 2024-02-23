<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Iterator\Contract\FluentIteratorInterface;
use Salient\Container\Contract\HasService;
use Salient\Container\Contract\ServiceAwareInterface;
use Salient\Core\Catalog\Conformity;

/**
 * Serviced by a provider
 *
 * @template TProvider of IProvider
 * @template TContext of IProviderContext
 *
 * @extends ReceivesProvider<TProvider>
 * @extends ReceivesProviderContext<TContext>
 * @extends HasProvider<TProvider>
 * @extends HasProviderContext<TContext>
 */
interface IProvidable extends
    ReceivesProvider,
    ReceivesProviderContext,
    ServiceAwareInterface,
    HasProvider,
    HasProviderContext,
    HasService
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
        IProvider $provider,
        ?IProviderContext $context = null
    );

    /**
     * Create instances of the class from arrays on behalf of a provider
     *
     * @param iterable<array-key,mixed[]> $list
     * @param TProvider $provider
     * @param Conformity::* $conformity
     * @param TContext|null $context
     * @return FluentIteratorInterface<array-key,static>
     */
    public static function provideList(
        iterable $list,
        IProvider $provider,
        $conformity = Conformity::NONE,
        ?IProviderContext $context = null
    ): FluentIteratorInterface;

    /**
     * Called after data from the provider has been applied to the object
     */
    public function postLoad(): void;
}
