<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Extensible;
use Salient\Contract\Core\ListConformity;
use Salient\Contract\Core\Providable;
use Salient\Contract\Core\ProviderContextInterface;
use Salient\Contract\Core\ProviderInterface;
use Salient\Contract\Iterator\FluentIteratorInterface;
use Salient\Core\Exception\LogicException;
use Salient\Core\Introspector;
use Salient\Iterator\IterableIterator;
use Generator;

/**
 * Implements Providable to represent an external entity
 *
 * @template TProvider of ProviderInterface
 * @template TContext of ProviderContextInterface
 *
 * @see Providable
 */
trait ProvidableTrait
{
    /**
     * @var TProvider|null
     */
    private $Provider;

    /**
     * @var TContext|null
     */
    private $Context;

    /**
     * @var class-string|null
     */
    private $Service;

    /**
     * @param TProvider $provider
     * @return $this
     */
    final public function setProvider(ProviderInterface $provider)
    {
        if ($this->Provider) {
            throw new LogicException('Provider already set');
        }
        $this->Provider = $provider;

        return $this;
    }

    /**
     * @return TProvider|null
     */
    final public function getProvider(): ?ProviderInterface
    {
        return $this->Provider;
    }

    /**
     * @return TProvider
     */
    final public function requireProvider(): ProviderInterface
    {
        if (!$this->Provider) {
            throw new LogicException('Provider required');
        }
        return $this->Provider;
    }

    /**
     * @param TContext $context
     * @return $this
     */
    final public function setContext(ProviderContextInterface $context)
    {
        $this->Context = $context;

        return $this;
    }

    /**
     * @return TContext|null
     */
    final public function getContext(): ?ProviderContextInterface
    {
        return $this->Context;
    }

    /**
     * @param class-string $service
     */
    final public function setService(string $service): void
    {
        $this->Service = $service;
    }

    /**
     * @return class-string
     */
    final public function getService(): string
    {
        return $this->Service ?? static::class;
    }

    /**
     * @return TContext
     */
    final public function requireContext(): ProviderContextInterface
    {
        if (!$this->Context) {
            throw new LogicException('Context required');
        }

        return $this->Context;
    }

    /**
     * Create an instance of the class from an array on behalf of a provider
     *
     * The constructor (if any) is invoked with values from `$data`. If `$data`
     * values remain, they are assigned to writable properties. If further
     * values remain and the class implements {@see Extensible}, they are
     * assigned via {@see Extensible::setMetaProperty()}.
     *
     * `$data` keys, constructor parameters and writable properties are
     * normalised for comparison.
     *
     * @param mixed[] $data
     * @param TProvider $provider
     * @param TContext|null $context
     * @return static
     */
    final public static function provide(
        array $data,
        ProviderInterface $provider,
        ?ProviderContextInterface $context = null
    ) {
        /** @var ContainerInterface */
        $container = $context
            ? $context->getContainer()
            : $provider->getContainer();
        $container = $container->inContextOf(get_class($provider));

        $context = $context
            ? $context->withContainer($container)
            : $provider->getContext($container);

        $closure = Introspector::getService(
            $container, static::class
        )->getCreateProvidableFromClosure();

        return $closure($data, $provider, $context);
    }

    /**
     * Create instances of the class from arrays on behalf of a provider
     *
     * See {@see ProvidableTrait::provide()} for more information.
     *
     * @param iterable<array-key,mixed[]> $list
     * @param TProvider $provider
     * @param ListConformity::* $conformity
     * @param TContext|null $context
     * @return FluentIteratorInterface<array-key,static>
     */
    final public static function provideList(
        iterable $list,
        ProviderInterface $provider,
        $conformity = ListConformity::NONE,
        ?ProviderContextInterface $context = null
    ): FluentIteratorInterface {
        return IterableIterator::from(
            self::_provideList($list, $provider, $conformity, $context)
        );
    }

    /**
     * @param iterable<array-key,mixed[]> $list
     * @param TProvider $provider
     * @param ListConformity::* $conformity
     * @param TContext|null $context
     * @return Generator<array-key,static>
     */
    private static function _provideList(
        iterable $list,
        ProviderInterface $provider,
        $conformity,
        ?ProviderContextInterface $context
    ): Generator {
        /** @var ContainerInterface */
        $container = $context
            ? $context->getContainer()
            : $provider->getContainer();
        $container = $container->inContextOf(get_class($provider));

        /** @var ProviderContextInterface */
        $context = $context
            ? $context->withContainer($container)
            : $provider->getContext($container);
        $context = $context->withConformity($conformity);

        $introspector = Introspector::getService($container, static::class);

        foreach ($list as $key => $data) {
            if (!isset($closure)) {
                $closure =
                    in_array($conformity, [ListConformity::PARTIAL, ListConformity::COMPLETE], true)
                        ? $introspector->getCreateProvidableFromSignatureClosure(array_keys($data))
                        : $introspector->getCreateProvidableFromClosure();
            }

            yield $key => $closure($data, $provider, $context);
        }
    }
}
