<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IProvider;
use Lkrms\Contract\IProviderContext;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\Introspector;
use Lkrms\Support\ProviderContext;
use RuntimeException;

/**
 * Implements IProvidable to represent an external entity
 *
 * @see \Lkrms\Contract\IProvidable
 * @psalm-require-implements \Lkrms\Contract\IProvidable
 */
trait TProvidable
{
    /**
     * @var IProvider|null
     */
    private $_Provider;

    /**
     * @var IProviderContext|null
     */
    private $_Context;

    /**
     * @var string|null
     */
    private $_Service;

    final public function setProvider(IProvider $provider)
    {
        if ($this->_Provider) {
            throw new RuntimeException('Provider already set');
        }
        $this->_Provider = $provider;

        return $this;
    }

    final public function provider(): ?IProvider
    {
        return $this->_Provider;
    }

    final public function setContext(IProviderContext $context)
    {
        $this->_Context = $context;

        return $this;
    }

    final public function context(): ?IProviderContext
    {
        return $this->_Context;
    }

    final public function setService(string $id)
    {
        $this->_Service = $id;

        return $this;
    }

    final public function service(): string
    {
        return $this->_Service ?: static::class;
    }

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
     * @return static
     */
    final public static function provide(array $data, IProvider $provider, ?IProviderContext $context = null)
    {
        $container = ($context
                          ? $context->container()
                          : $provider->container())->inContextOf(get_class($provider));
        $context = $context
                       ? $context->withContainer($container)
                       : $container->get(ProviderContext::class);
        $introspector = Introspector::getService($container, static::class);
        $closure      = $introspector->getCreateProvidableFromClosure();

        return $closure($data, $provider, $context);
    }

    /**
     * Create instances of the class from arrays on behalf of a provider
     *
     * See {@see TProvidable::provide()} for more information.
     *
     * @param iterable<array> $dataList
     * @phpstan-param ArrayKeyConformity::* $conformity
     * @return iterable<static>
     */
    final public static function provideList(iterable $dataList, IProvider $provider, int $conformity = ArrayKeyConformity::NONE, ?IProviderContext $context = null): iterable
    {
        $container = ($context
                          ? $context->container()
                          : $provider->container())->inContextOf(get_class($provider));
        $context = ($context
                        ? $context->withContainer($container)
                        : new ProviderContext($container))->withConformity($conformity);
        $introspector = Introspector::getService($container, static::class);

        foreach ($dataList as $data) {
            if (!isset($closure)) {
                $closure =
                    in_array($conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                        ? $introspector->getCreateProvidableFromSignatureClosure(array_keys($data))
                        : $introspector->getCreateProvidableFromClosure();
            }

            yield $closure($data, $provider, $context);
        }
    }
}
