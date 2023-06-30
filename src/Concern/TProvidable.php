<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Generator;
use Lkrms\Contract\IProvider;
use Lkrms\Contract\IProviderContext;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Introspector;
use Lkrms\Support\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Support\Iterator\IterableIterator;
use Lkrms\Support\ProviderContext;
use RuntimeException;

/**
 * Implements IProvidable to represent an external entity
 *
 * @template TProvider of IProvider
 * @template TProviderContext of IProviderContext
 *
 * @see \Lkrms\Contract\IProvidable
 */
trait TProvidable
{
    /**
     * @var TProvider|null
     */
    private $_Provider;

    /**
     * @var TProviderContext|null
     */
    private $_Context;

    /**
     * @var class-string|null
     */
    private $_Service;

    /**
     * @param TProvider $provider
     * @return $this
     */
    final public function setProvider(IProvider $provider)
    {
        if ($this->_Provider) {
            throw new RuntimeException('Provider already set');
        }
        $this->_Provider = $provider;

        return $this;
    }

    /**
     * @return TProvider|null
     */
    final public function provider(): ?IProvider
    {
        return $this->_Provider;
    }

    /**
     * @param TProviderContext $context
     * @return $this
     */
    final public function setContext(IProviderContext $context)
    {
        $this->_Context = $context;

        return $this;
    }

    /**
     * @return TProviderContext|null
     */
    final public function context(): ?IProviderContext
    {
        return $this->_Context;
    }

    /**
     * @param class-string $id
     * @return $this
     */
    final public function setService(string $id)
    {
        $this->_Service = $id;

        return $this;
    }

    /**
     * @return class-string
     */
    final public function service(): string
    {
        return $this->_Service ?: static::class;
    }

    /**
     * @return TProviderContext
     */
    final public function requireContext(): IProviderContext
    {
        if (!$this->_Context) {
            throw new RuntimeException('Context required');
        }

        return $this->_Context;
    }

    /**
     * Create an instance of the class from an array on behalf of a provider
     *
     * The constructor (if any) is invoked with values from `$data`. If `$data`
     * values remain, they are assigned to writable properties. If further
     * values remain and the class implements
     * {@see \Lkrms\Contract\IExtensible}, they are assigned via
     * {@see \Lkrms\Contract\IExtensible::setMetaProperty()}.
     *
     * `$data` keys, constructor parameters and writable properties are
     * normalised for comparison.
     *
     * @param mixed[] $data
     * @param TProvider $provider
     * @param TProviderContext|null $context
     * @return static
     */
    final public static function provide(
        array $data,
        IProvider $provider,
        ?IProviderContext $context = null
    ) {
        $container = ($context
            ? $context->container()
            : $provider->container())->inContextOf(get_class($provider));
        $context = $context
            ? $context->withContainer($container)
            : $container->get(ProviderContext::class);
        $introspector = Introspector::getService($container, static::class);
        $closure = $introspector->getCreateProvidableFromClosure();

        return $closure($data, $provider, $context);
    }

    /**
     * Create instances of the class from arrays on behalf of a provider
     *
     * See {@see TProvidable::provide()} for more information.
     *
     * @param iterable<array-key,mixed[]> $dataList
     * @param TProvider $provider
     * @phpstan-param ArrayKeyConformity::* $conformity
     * @param TProviderContext|null $context
     * @return FluentIteratorInterface<array-key,static>
     */
    final public static function provideList(
        iterable $dataList,
        IProvider $provider,
        int $conformity = ArrayKeyConformity::NONE,
        ?IProviderContext $context = null
    ): FluentIteratorInterface {
        return IterableIterator::from(
            self::_provideList($dataList, $provider, $conformity, $context)
        );
    }

    /**
     * @param iterable<array-key,mixed[]> $dataList
     * @param TProvider $provider
     * @phpstan-param ArrayKeyConformity::* $conformity
     * @param TProviderContext|null $context
     * @return Generator<array-key,static>
     */
    private static function _provideList(
        iterable $dataList,
        IProvider $provider,
        int $conformity,
        ?IProviderContext $context
    ): Generator {
        $container = ($context
            ? $context->container()
            : $provider->container())->inContextOf(get_class($provider));
        $context = ($context
            ? $context->withContainer($container)
            : $container->get(ProviderContext::class))->withConformity($conformity);
        $introspector = Introspector::getService($container, static::class);

        foreach ($dataList as $key => $data) {
            if (!isset($closure)) {
                $closure =
                    in_array($conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                        ? $introspector->getCreateProvidableFromSignatureClosure(array_keys($data))
                        : $introspector->getCreateProvidableFromClosure();
            }

            yield $key => $closure($data, $provider, $context);
        }
    }
}
