<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\HasMutator;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IHierarchy;
use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IProviderContext;

/**
 * The context within which an IProvidable is instantiated
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
     * @var array<string,mixed>
     */
    protected $Values = [];

    /**
     * @var IProvidable[]
     */
    protected $Stack = [];

    /**
     * @var IHierarchy|null
     */
    protected $Parent;

    /**
     * @var int
     * @psalm-var ArrayKeyConformity::*
     */
    protected $Conformity;

    /**
     * @psalm-param ArrayKeyConformity::* $conformity
     */
    public function __construct(IContainer $container, ?IHierarchy $parent = null, int $conformity = ArrayKeyConformity::NONE)
    {
        $this->Container  = $container;
        $this->Parent     = $parent;
        $this->Conformity = $conformity;
    }

    final public function app(): IContainer
    {
        return $this->Container;
    }

    final public function container(): IContainer
    {
        return $this->Container;
    }

    final public function set(string $key, $value)
    {
        return $this->withPropertyValue('Values', $value, $key);
    }

    final public function push(IProvidable $entity)
    {
        $clone          = clone $this;
        $clone->Stack[] = $entity;

        return $clone;
    }

    final public function withContainer(IContainer $container)
    {
        return $this->withPropertyValue('Container', $container);
    }

    final public function withParent(?IHierarchy $parent)
    {
        return $this->withPropertyValue('Parent', $parent);
    }

    final public function withConformity(int $conformity)
    {
        return $this->withPropertyValue('Conformity', $conformity);
    }

    final public function get(string $key)
    {
        return $this->Values[$key] ?? false;
    }

    final public function getStack(): array
    {
        return $this->Stack;
    }

    final public function getParent(): ?IHierarchy
    {
        return $this->Parent;
    }

    final public function getConformity(): int
    {
        return $this->Conformity;
    }
}
