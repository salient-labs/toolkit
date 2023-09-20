<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Iterator\Contract\FluentIteratorInterface;

/**
 * Can be serviced by a provider
 *
 * @template TProvider of IProvider
 * @template TProviderContext of IProviderContext
 *
 * @extends ReceivesProvider<TProvider>
 * @extends ReceivesProviderContext<TProviderContext>
 * @extends ReturnsProvider<TProvider>
 * @extends ReturnsProviderContext<TProviderContext>
 */
interface IProvidable extends
    ReceivesProvider,
    ReceivesProviderContext,
    ReceivesService,
    ReturnsProvider,
    ReturnsProviderContext,
    ReturnsService
{
    /**
     * Create an instance of the class from an array on behalf of a provider
     *
     * @param mixed[] $data
     * @param TProvider $provider
     * @param TProviderContext|null $context
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
     * @param ArrayKeyConformity::* $conformity
     * @param TProviderContext|null $context
     * @return FluentIteratorInterface<array-key,static>
     */
    public static function provideList(
        iterable $list,
        IProvider $provider,
        int $conformity = ArrayKeyConformity::NONE,
        ?IProviderContext $context = null
    ): FluentIteratorInterface;

    /**
     * Called after data from the provider has been applied to the object
     */
    public function postLoad(): void;
}
