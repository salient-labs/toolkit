<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\HasContainer;

/**
 * The context within which entities of a given type are instantiated by a
 * provider
 *
 * @template TProvider of ProviderInterface
 * @template TEntity of Providable
 *
 * @extends HasProvider<TProvider>
 */
interface ProviderContextInterface extends
    Immutable,
    HasContainer,
    HasProvider
{
    /**
     * Apply a container to the context
     *
     * @return static
     */
    public function withContainer(ContainerInterface $container);

    /**
     * Push the entity propagating the context onto the stack
     *
     * Note that although the same entity may be passed to both
     * {@see ProviderContextInterface::push()} and
     * {@see ProviderContextInterface::withParent()} (e.g. when a hierarchy is
     * being populated from a root entity), they serve different purposes.
     *
     * Example: a `Post` object would `push()` itself onto the entity stack to
     * retrieve a `User` instance for its `Author` property, but a `Staff`
     * object would `push()` itself onto the entity stack to retrieve `Staff`
     * instances for its `DirectReports` property, **and** pass itself to
     * `withParent()` as the parent (a.k.a. manager) of those `Staff`.
     *
     * Pushing an entity that implements {@see HasId} onto the stack implicitly
     * adds its unique identifier to the context as a value with name
     * `<entity_basename>_id` if {@see HasId::getId()} returns a value other
     * than `null`.
     *
     * @param TEntity $entity
     * @return static
     */
    public function push($entity);

    /**
     * Apply a value to the context
     *
     * @param (int|string|float|bool|null)[]|int|string|float|bool|null $value
     * @return static
     */
    public function withValue(string $name, $value);

    /**
     * Apply a parent entity to the context
     *
     * @see ProviderContextInterface::push()
     *
     * @param (TEntity&Treeable)|null $parent
     * @return static
     */
    public function withParent(?Treeable $parent);

    /**
     * Apply the current payload's array key conformity to the context
     *
     * @param ListConformity::* $conformity Use {@see ListConformity::COMPLETE}
     * wherever possible to improve performance.
     * @return static
     */
    public function withConformity($conformity);

    /**
     * @return TProvider
     */
    public function getProvider(): ProviderInterface;

    /**
     * Get the entities responsible for propagating this context
     *
     * @return TEntity[]
     */
    public function stack(): array;

    /**
     * Get the entity responsible for the most recent propagation of this
     * context
     *
     * @return TEntity|null
     */
    public function last(): ?Providable;

    /**
     * Get the parent entity applied to the context
     *
     * @return (TEntity&Treeable)|null
     */
    public function getParent(): ?Treeable;

    /**
     * Get a value previously applied to the context
     *
     * Returns `null` if no value for `$name` has been applied to the context.
     *
     * @return (int|string|float|bool|null)[]|int|string|float|bool|null
     */
    public function getValue(string $name);

    /**
     * True if a value was previously applied to the context
     */
    public function hasValue(string $name): bool;

    /**
     * Get the current payload's array key conformity
     *
     * @return ListConformity::*
     */
    public function getConformity();
}
