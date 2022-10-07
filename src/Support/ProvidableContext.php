<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProvidableContext;
use Lkrms\Contract\IHierarchy;
use Lkrms\Contract\IProvidable;
use RuntimeException;

/**
 * The context within which an IProvidable is instantiated
 *
 */
class ProvidableContext implements IProvidableContext
{
    /**
     * @var IContainer
     */
    private $Container;

    /**
     * @var array<string,mixed>
     */
    private $Values = [];

    /**
     * @var IProvidable[]
     */
    private $Stack = [];

    /**
     * @var IHierarchy|null
     */
    private $Parent;

    public function __construct(IContainer $container, ?IHierarchy $parent = null)
    {
        $this->Container = $container;
        $this->Parent    = $parent;
    }

    private function maybeMutate(string $property, $value, ?string $key = null)
    {
        if ($key)
        {
            if (!is_array($this->{$property}))
            {
                throw new RuntimeException("\$this->{$property} is not an array");
            }
            $_value       = $this->{$property};
            $_value[$key] = $value;
            $value        = $_value;
        }
        if ($value === $this->{$property})
        {
            return $this;
        }
        $clone = clone $this;
        $clone->{$property} = $value;

        return $clone;
    }

    public function app(): IContainer
    {
        return $this->Container;
    }

    public function container(): IContainer
    {
        return $this->Container;
    }

    public function set(string $key, $value)
    {
        return $this->maybeMutate("Values", $value, $key);
    }

    public function push(IProvidable $entity)
    {
        $clone = clone $this;
        $clone->Stack[] = $entity;

        return $clone;
    }

    public function withContainer(IContainer $container)
    {
        return $this->maybeMutate("Container", $container);
    }

    public function withParent(?IHierarchy $parent)
    {
        return $this->maybeMutate("Parent", $parent);
    }

    public function get(string $key)
    {
        return $this->Values[$key] ?? false;
    }

    public function getStack(): array
    {
        return $this->Stack;
    }

    public function getParent(): ?IHierarchy
    {
        return $this->Parent;
    }

}
