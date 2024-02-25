<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Container\ContainerInterface;
use Salient\Core\Catalog\Conformity;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Contract\HasIdentifier;
use Salient\Core\Contract\IProvidable;
use Salient\Core\Contract\IProvider;
use Salient\Core\Contract\IProviderContext;
use Salient\Core\Contract\ITreeable;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Str;

/**
 * The context within which entities of a given type are instantiated by a
 * provider
 *
 * @template TProvider of IProvider
 * @template TEntity of IProvidable
 *
 * @implements IProviderContext<TProvider,TEntity>
 */
class ProviderContext implements IProviderContext
{
    use HasImmutableProperties;

    protected ContainerInterface $Container;

    /**
     * @var TProvider
     */
    protected IProvider $Provider;

    /**
     * @var TEntity[]
     */
    protected array $Stack = [];

    /**
     * @var array<string,mixed>
     */
    protected array $Values = [];

    /**
     * @var (TEntity&ITreeable)|null
     */
    protected ?ITreeable $Parent = null;

    /**
     * @var Conformity::*
     */
    protected $Conformity = Conformity::NONE;

    /**
     * Creates a new ProviderContext object
     *
     * @param TProvider $provider
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
