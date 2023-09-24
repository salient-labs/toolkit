<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\HasMutator;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IProvider;
use Lkrms\Contract\IProviderContext;
use Lkrms\Contract\ITreeable;
use Lkrms\Support\Catalog\ArrayKeyConformity;

/**
 * The context within which an entity is instantiated by a provider
 *
 */
class ProviderContext implements IProviderContext
{
    use HasMutator;

    /**
     * @var IContainer
     */
    protected $Container;

    /**
     * @var IProvider
     */
    protected $Provider;

    /**
     * @var IProvidable<IProvider,IProviderContext>[]
     */
    protected $Stack = [];

    /**
     * @var ITreeable|null
     */
    protected $Parent;

    /**
     * @var ArrayKeyConformity::*
     */
    protected $Conformity;

    /**
     * @param ArrayKeyConformity::* $conformity
     */
    public function __construct(
        IContainer $container,
        IProvider $provider,
        ?ITreeable $parent = null,
        int $conformity = ArrayKeyConformity::NONE
    ) {
        $this->Container = $container;
        $this->Provider = $provider;
        $this->Parent = $parent;
        $this->Conformity = $conformity;
    }

    /**
     * @inheritDoc
     */
    final public function app(): IContainer
    {
        return $this->Container;
    }

    /**
     * @inheritDoc
     */
    final public function container(): IContainer
    {
        return $this->Container;
    }

    /**
     * @inheritDoc
     */
    final public function provider(): IProvider
    {
        return $this->Provider;
    }

    /**
     * @inheritDoc
     */
    final public function requireProvider(): IProvider
    {
        return $this->Provider;
    }

    /**
     * @inheritDoc
     */
    final public function push(IProvidable $entity)
    {
        $clone = $this->mutate();
        $clone->Stack[] = $entity;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    final public function withContainer(IContainer $container)
    {
        return $this->withPropertyValue('Container', $container);
    }

    /**
     * @inheritDoc
     */
    final public function withParent(?ITreeable $parent)
    {
        return $this->withPropertyValue('Parent', $parent);
    }

    /**
     * @inheritDoc
     */
    final public function withConformity($conformity)
    {
        return $this->withPropertyValue('Conformity', $conformity);
    }

    /**
     * @inheritDoc
     */
    final public function stack(): array
    {
        return $this->Stack;
    }

    /**
     * @inheritDoc
     */
    final public function getParent(): ?ITreeable
    {
        return $this->Parent;
    }

    /**
     * @inheritDoc
     */
    final public function getConformity(): int
    {
        return $this->Conformity;
    }
}
