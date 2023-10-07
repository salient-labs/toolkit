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
 * @implements IProviderContext<IProvider<static>,IProvidable<IProvider<static>,static>>
 */
class ProviderContext implements IProviderContext
{
    use HasMutator;

    protected IContainer $Container;

    /**
     * @var IProvider<static>
     */
    protected IProvider $Provider;

    /**
     * @var array<IProvidable<IProvider<static>,static>>
     */
    protected array $Stack = [];

    /**
     * @var array<string,mixed>
     */
    protected array $Values = [];

    /**
     * @var (IProvidable<IProvider<static>,static>&ITreeable)|null
     */
    protected ?ITreeable $Parent = null;

    /**
     * @var ArrayKeyConformity::*
     */
    protected $Conformity = ArrayKeyConformity::NONE;

    /**
     * Creates a new ProviderContext object
     *
     * @param IProvider<static> $provider
     */
    public function __construct(
        IContainer $container,
        IProvider $provider
    ) {
        $this->Container = $container;
        $this->Provider = $provider;
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
    final public function withContainer(IContainer $container)
    {
        return $this->withPropertyValue('Container', $container);
    }

    /**
     * @inheritDoc
     */
    final public function push($entity)
    {
        $clone = $this->mutate();
        $clone->Stack[] = $entity;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    final public function withValue(string $name, $value)
    {
        return $this->withPropertyValue('Values', $value, $name);
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
    final public function last(): ?IProvidable
    {
        return end($this->Stack) ?: null;
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
    final public function getValue(string $name)
    {
        return $this->Values[$name] ?? null;
    }

    /**
     * @inheritDoc
     */
    final public function hasValue(string $name): bool
    {
        return array_key_exists($name, $this->Values);
    }

    /**
     * @inheritDoc
     */
    final public function getConformity(): int
    {
        return $this->Conformity;
    }
}
