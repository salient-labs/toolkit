<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Entity\Providable;
use Salient\Contract\Core\Provider\ProviderContextInterface;
use Salient\Contract\Core\Provider\ProviderInterface;
use Salient\Contract\Core\ListConformity;
use Salient\Core\Introspector;
use LogicException;

/**
 * Implements Providable to represent an external entity
 *
 * @see Providable
 *
 * @template TProvider of ProviderInterface
 * @template TContext of ProviderContextInterface
 */
trait ProvidableTrait
{
    /** @var TProvider|null */
    private $Provider;
    /** @var TContext|null */
    private $Context;
    /** @var class-string|null */
    private $Service;

    /**
     * @inheritDoc
     */
    public function postLoad(): void {}

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
     * @param mixed[] $data
     * @param TContext $context
     * @return static
     */
    final public static function provide(
        array $data,
        ProviderContextInterface $context
    ) {
        $provider = $context->getProvider();
        $container = $context
            ->getContainer()
            ->inContextOf(get_class($provider));
        $context = $context->withContainer($container);

        $closure = Introspector::getService($container, static::class)
            ->getCreateProvidableFromClosure();

        return $closure($data, $provider, $context);
    }

    /**
     * @template TKey of array-key
     *
     * @param iterable<TKey,mixed[]> $data
     * @param TContext $context
     * @param ListConformity::* $conformity
     * @return iterable<TKey,static>
     */
    final public static function provideMultiple(
        iterable $data,
        ProviderContextInterface $context,
        int $conformity = ListConformity::NONE
    ): iterable {
        return self::_provideMultiple($data, $context, $conformity);
    }

    /**
     * @template TKey of array-key
     *
     * @param iterable<TKey,mixed[]> $data
     * @param TContext $context
     * @param ListConformity::* $conformity
     * @return iterable<TKey,static>
     */
    private static function _provideMultiple(
        iterable $data,
        ProviderContextInterface $context,
        int $conformity = ListConformity::NONE
    ): iterable {
        $provider = $context->getProvider();
        $container = $context
            ->getContainer()
            ->inContextOf(get_class($provider));
        $context = $context->withContainer($container);
        $conformity = max($context->getConformity(), $conformity);
        $introspector = Introspector::getService($container, static::class);

        foreach ($data as $key => $data) {
            if (!isset($closure)) {
                $closure = $conformity === ListConformity::PARTIAL || $conformity === ListConformity::COMPLETE
                    ? $introspector->getCreateProvidableFromSignatureClosure(array_keys($data))
                    : $introspector->getCreateProvidableFromClosure();
            }

            yield $key => $closure($data, $provider, $context);
        }
    }
}
