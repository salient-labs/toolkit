<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Entity\Treeable;
use Salient\Contract\Core\Provider\Providable;
use Salient\Contract\Core\Provider\ProviderContextInterface;
use Salient\Contract\Core\Provider\ProviderInterface;
use Salient\Contract\Core\HasId;
use Salient\Contract\Core\ListConformity;
use Salient\Core\Concern\HasMutator;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Str;

/**
 * The context within which entities of a given type are instantiated by a
 * provider
 *
 * @api
 *
 * @template TProvider of ProviderInterface
 * @template TEntity of Providable
 *
 * @implements ProviderContextInterface<TProvider,TEntity>
 */
class ProviderContext implements ProviderContextInterface
{
    use HasMutator;

    protected ContainerInterface $Container;
    /** @var TProvider */
    protected ProviderInterface $Provider;
    /** @var class-string<TEntity>|null */
    protected ?string $EntityType = null;
    /** @var TEntity[] */
    protected array $Entities = [];
    /** @var array<string,(int|string|float|bool|null)[]|int|string|float|bool|null> */
    protected array $Values = [];
    /** @var (TEntity&Treeable)|null */
    protected ?Treeable $Parent = null;
    /** @var ListConformity::* */
    protected $Conformity = ListConformity::NONE;

    /**
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
    public function getProvider(): ProviderInterface
    {
        return $this->Provider;
    }

    /**
     * @inheritDoc
     */
    public function getContainer(): ContainerInterface
    {
        return $this->Container;
    }

    /**
     * @inheritDoc
     */
    public function withContainer(ContainerInterface $container)
    {
        return $this->with('Container', $container);
    }

    /**
     * @inheritDoc
     */
    public function getEntityType(): ?string
    {
        return $this->EntityType;
    }

    /**
     * @inheritDoc
     */
    public function withEntityType(string $entityType)
    {
        return $this->with('EntityType', $entityType);
    }

    /**
     * @inheritDoc
     */
    public function getConformity(): int
    {
        return $this->Conformity;
    }

    /**
     * @inheritDoc
     */
    public function withConformity(int $conformity)
    {
        return $this->with('Conformity', $conformity);
    }

    /**
     * @inheritDoc
     */
    public function getEntities(): array
    {
        return $this->Entities;
    }

    /**
     * @inheritDoc
     */
    public function getLastEntity(): ?Providable
    {
        return Arr::last($this->Entities);
    }

    /**
     * @inheritDoc
     */
    public function pushEntity($entity)
    {
        $clone = clone $this;
        $clone->Entities[] = $entity;

        if ($entity instanceof HasId) {
            $id = $entity->getId();
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
    public function getParent(): ?Providable
    {
        return $this->Parent;
    }

    /**
     * @inheritDoc
     */
    public function withParent(?Treeable $parent)
    {
        return $this->with('Parent', $parent);
    }

    /**
     * @inheritDoc
     */
    public function hasValue(string $name): bool
    {
        $name = Str::snake($name);

        if (array_key_exists($name, $this->Values)) {
            return true;
        }

        if (substr($name, -3) !== '_id') {
            return false;
        }

        $name = Str::snake(substr($name, 0, -3));

        return array_key_exists($name, $this->Values);
    }

    /**
     * @inheritDoc
     */
    public function getValue(string $name)
    {
        $name = Str::snake($name);

        if (array_key_exists($name, $this->Values)) {
            return $this->Values[$name];
        }

        if (substr($name, -3) !== '_id') {
            return null;
        }

        $name = Str::snake(substr($name, 0, -3));

        return $this->Values[$name] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function withValue(string $name, $value)
    {
        $name = Str::snake($name);
        $values = $this->Values;
        $values[$name] = $value;

        if (substr($name, -3) === '_id') {
            $name = Str::snake(substr($name, 0, -3));
            $values[$name] = $value;
        }

        return $this->with('Values', $values);
    }
}
