<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\Immutable;
use Lkrms\Container\Contract\ContainerInterface;
use Lkrms\Contract\HasIdentifier;
use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IProvider;
use Lkrms\Contract\IProviderContext;
use Lkrms\Contract\ITreeable;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Utility\Get;
use Lkrms\Utility\Str;

/**
 * The context within which an entity is instantiated by a provider
 *
 * @implements IProviderContext<IProvider<static>,IProvidable<IProvider<static>,static>>
 */
class ProviderContext implements IProviderContext
{
    use Immutable;

    protected ContainerInterface $Container;

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
        ContainerInterface $container,
        IProvider $provider
    ) {
        $this->Container = $container;
        $this->Provider = $provider;
    }

    /**
     * @inheritDoc
     */
    final public function app(): ContainerInterface
    {
        return $this->Container;
    }

    /**
     * @inheritDoc
     */
    final public function container(): ContainerInterface
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
    final public function withContainer(ContainerInterface $container)
    {
        return $this->withPropertyValue('Container', $container);
    }

    /**
     * @inheritDoc
     */
    final public function push($entity)
    {
        $clone = $this->clone();
        $clone->Stack[] = $entity;

        if ($entity instanceof HasIdentifier) {
            $id = $entity->id();
            if ($id !== null) {
                $name = Get::basename(get_class($entity));
                return $clone->withValue("{$name}_id", $id);
            }
        }

        return $clone;
    }

    /**
     * @inheritDoc
     */
    final public function withValue(string $name, $value)
    {
        $name = Str::toSnakeCase($name);
        $values = $this->Values;
        $values[$name] = $value;

        if (substr($name, -3) === '_id') {
            $name = Str::toSnakeCase(substr($name, 0, -3));
            if ($name !== '') {
                $values[$name] = $value;
            }
        }

        return $this->withPropertyValue('Values', $values);
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
        $name = Str::toSnakeCase($name);

        if (array_key_exists($name, $this->Values)) {
            return $this->Values[$name];
        }

        if (substr($name, -3) !== '_id') {
            return null;
        }

        $name = Str::toSnakeCase(substr($name, 0, -3));

        return $this->Values[$name] ?? null;
    }

    /**
     * @inheritDoc
     */
    final public function hasValue(string $name): bool
    {
        $name = Str::toSnakeCase($name);

        if (array_key_exists($name, $this->Values)) {
            return true;
        }

        if (substr($name, -3) !== '_id') {
            return false;
        }

        $name = Str::toSnakeCase(substr($name, 0, -3));

        return array_key_exists($name, $this->Values);
    }

    /**
     * @inheritDoc
     */
    final public function getConformity()
    {
        return $this->Conformity;
    }
}
