<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Salient\Container\Contract\HasContainer;
use Salient\Container\ContainerInterface;
use Salient\Core\Catalog\Conformity;
use Salient\Core\Contract\Immutable;

/**
 * The context within which entities of a given type are instantiated by a
 * provider
 *
 * @template TProvider of IProvider
 * @template TEntity of IProvidable
 *
 * @extends HasContainer<ContainerInterface>
 * @extends HasProvider<TProvider>
 */
interface IProviderContext extends
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
     * {@see IProviderContext::push()} and {@see IProviderContext::withParent()}
     * (e.g. when a hierarchy is being populated from a root entity), they serve
     * different purposes.
     *
     * Example: a `Post` object would `push()` itself onto the entity stack to
     * retrieve a `User` instance for its `Author` property, but a `Staff`
     * object would `push()` itself onto the entity stack to retrieve `Staff`
     * instances for its `DirectReports` property, **and** pass itself to
     * `withParent()` as the parent (a.k.a. manager) of those `Staff`.
     *
     * Pushing an entity that implements {@see HasIdentifier} onto the stack
     * implicitly adds its unique identifier to the context as a value with name
     * `<entity_basename>_id` if {@see HasIdentifier::id()} returns a value
     * other than `null`.
     *
     * @param TEntity $entity
     * @return static
     */
    public function push($entity);

    /**
     * Apply a value to the context
     *
     * @param mixed $value
     * @return static
     */
    public function withValue(string $name, $value);

    /**
     * Apply a parent entity to the context
     *
     * @see IProviderContext::push()
     *
     * @param (TEntity&ITreeable)|null $parent
     * @return static
     */
    public function withParent(?ITreeable $parent);

    /**
     * Apply the current payload's array key conformity to the context
     *
     * @param Conformity::* $conformity Use
     * {@see Conformity::COMPLETE} wherever possible to improve
     * performance.
     * @return static
     */
    public function withConformity($conformity);

    /**
     * @return TProvider
     */
    public function provider(): IProvider;

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
    public function last(): ?IProvidable;

    /**
     * Get the parent entity applied to the context
     *
     * @return (TEntity&ITreeable)|null
     */
    public function getParent(): ?ITreeable;

    /**
     * Get a value previously applied to the context
     *
     * Returns `null` if no value for `$name` has been applied to the context.
     *
     * @return mixed|null
     */
    public function getValue(string $name);

    /**
     * True if a value was previously applied to the context
     */
    public function hasValue(string $name): bool;

    /**
     * Get the current payload's array key conformity
     *
     * @return Conformity::*
     */
    public function getConformity();
}
