<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Identifiable;
use Salient\Contract\Core\ListConformity;
use Salient\Contract\Core\Providable;
use Salient\Contract\Core\ProviderContextInterface;
use Salient\Contract\Core\ProviderInterface;
use Salient\Contract\Core\Treeable;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Str;

/**
 * The context within which entities of a given type are instantiated by a
 * provider
 *
 * @template TProvider of ProviderInterface
 * @template TEntity of Providable
 *
 * @implements ProviderContextInterface<TProvider,TEntity>
 */
class ProviderContext implements ProviderContextInterface
{
    use HasImmutableProperties;

    protected ContainerInterface $Container;
    /** @var TProvider */
    protected ProviderInterface $Provider;
    /** @var TEntity[] */
    protected array $Stack = [];
    /** @var array<string,(int|string|float|bool|null)[]|int|string|float|bool|null> */
    protected array $Values = [];
    /** @var (TEntity&Treeable)|null */
    protected ?Treeable $Parent = null;
    /** @var ListConformity::* */
    protected $Conformity = ListConformity::NONE;

    /**
     * Creates a new ProviderContext object
     *
     * @param TProvider $provider
     */
    public function __construct(
        ContainerInterface $container,
        ProviderInterface $provider
    ) {
        $this->Container = $container;
        $this->Provider = $provider;
    }

    /**
     * @inheritDoc
     */
    final public function getContainer(): ContainerInterface
    {
        return $this->Container;
    }

    /**
     * @inheritDoc
     */
    final public function getProvider(): ProviderInterface
    {
        return $this->Provider;
    }

    /**
     * @inheritDoc
     */
    final public function requireProvider(): ProviderInterface
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

        if ($entity instanceof Identifiable) {
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
    final public function withParent(?Treeable $parent)
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
    final public function last(): ?Providable
    {
        return end($this->Stack) ?: null;
    }

    /**
     * @inheritDoc
     */
    final public function getParent(): ?Treeable
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
