<?php declare(strict_types=1);

namespace Salient\Contract\Core\Provider;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\HasContainer;
use Salient\Contract\Core\Entity\Providable;
use Salient\Contract\Core\Entity\Treeable;
use Salient\Contract\Core\Immutable;
use Salient\Contract\HasConformity;

/**
 * @api
 *
 * @template TProvider of ProviderInterface
 * @template TEntity of Providable
 *
 * @extends HasProvider<TProvider>
 */
interface ProviderContextInterface extends
    HasProvider,
    HasContainer,
    Immutable,
    HasConformity
{
    /**
     * Get the context's provider
     *
     * @return TProvider
     */
    public function getProvider(): ProviderInterface;

    /**
     * Get an instance with the given container
     *
     * @return static
     */
    public function withContainer(ContainerInterface $container);

    /**
     * Get the entity type applied to the context
     *
     * @return class-string<TEntity>|null
     */
    public function getEntityType(): ?string;

    /**
     * Get an instance with the given entity type
     *
     * @param class-string<TEntity> $entityType
     * @return static
     */
    public function withEntityType(string $entityType);

    /**
     * Get the conformity level applied to the context
     *
     * @return ProviderContextInterface::*
     */
    public function getConformity(): int;

    /**
     * Get an instance with the given conformity level
     *
     * @param ProviderContextInterface::* $conformity Use
     * {@see ProviderContextInterface::CONFORMITY_COMPLETE} wherever possible to
     * improve performance.
     * @return static
     */
    public function withConformity(int $conformity);

    /**
     * Get entities for which the context has been propagated
     *
     * @return TEntity[]
     */
    public function getEntities(): array;

    /**
     * Get the entity for which the context was most recently propagated
     *
     * @return TEntity|null
     */
    public function getLastEntity(): ?Providable;

    /**
     * Add the entity for which the context is being propagated
     *
     * If `$entity` implements {@see HasId} and the return value of
     * {@see HasId::getId()} is not `null`, it is applied to the context as a
     * value with name `<entity_basename>_id`.
     *
     * @param TEntity $entity
     * @return static
     */
    public function pushEntity($entity);

    /**
     * Get the parent entity applied to the context
     *
     * @return (TEntity&Treeable)|null
     */
    public function getParent(): ?Providable;

    /**
     * Get an instance with the given parent entity
     *
     * @param (TEntity&Treeable)|null $parent
     * @return static
     */
    public function withParent(?Treeable $parent);

    /**
     * Check if a value has been applied to the context
     */
    public function hasValue(string $name): bool;

    /**
     * Get a value applied to the context, or null if it has not been applied
     *
     * @return (int|string|float|bool|null)[]|int|string|float|bool|null
     */
    public function getValue(string $name);

    /**
     * Get an instance with the given value applied
     *
     * @param (int|string|float|bool|null)[]|int|string|float|bool|null $value
     * @return static
     */
    public function withValue(string $name, $value);
}
