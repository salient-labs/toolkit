<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Catalog\ListConformity;
use Salient\Contract\Core\Entity\Providable;
use Salient\Contract\Core\Provider\ProviderContextInterface;
use Salient\Contract\Core\Provider\ProviderInterface;
use Salient\Core\Legacy\Introspector;
use LogicException;

/**
 * @api
 *
 * @template TProvider of ProviderInterface
 * @template TContext of ProviderContextInterface
 *
 * @phpstan-require-implements Providable<TProvider,TContext>
 */
trait ProvidableTrait
{
    /** @var TProvider|null */
    private ?ProviderInterface $Provider = null;
    /** @var TContext|null */
    private ?ProviderContextInterface $Context = null;
    /** @var class-string|null */
    private ?string $Service = null;

    /**
     * @param TProvider $provider
     */
    public function setProvider(ProviderInterface $provider)
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
    public function getProvider(): ?ProviderInterface
    {
        return $this->Provider;
    }

    /**
     * @param TContext $context
     */
    public function setContext(ProviderContextInterface $context)
    {
        $this->Context = $context;
        return $this;
    }

    /**
     * @return TContext|null
     */
    public function getContext(): ?ProviderContextInterface
    {
        return $this->Context;
    }

    /**
     * @inheritDoc
     */
    public function setService(string $service): void
    {
        $this->Service = $service;
    }

    /**
     * @inheritDoc
     */
    public function getService(): string
    {
        return $this->Service ?? static::class;
    }

    /**
     * @param TContext $context
     */
    public static function provide(
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
     * @param TContext $context
     */
    public static function provideMultiple(
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
            $closure ??= $conformity === ListConformity::PARTIAL || $conformity === ListConformity::COMPLETE
                ? $introspector->getCreateProvidableFromSignatureClosure(array_keys($data))
                : $introspector->getCreateProvidableFromClosure();

            yield $key => $closure($data, $provider, $context);
        }
    }

    /**
     * @inheritDoc
     */
    public function postLoad(): void {}
}
