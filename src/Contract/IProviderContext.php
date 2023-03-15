<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\ArrayKeyConformity;

/**
 * The context within which an IProvidable is instantiated
 *
 */
interface IProviderContext extends IImmutable, ReturnsContainer
{
    /**
     * Apply an arbitrary value to the context
     *
     * @return $this
     */
    public function set(string $key, $value);

    /**
     * Push the entity propagating this context onto the stack
     *
     * Note that although the same entity may be passed to both
     * {@see IProviderContext::push()} and {@see IProviderContext::withParent()}
     * (e.g. when a hierarchy is being populated from a root entity), they have
     * completely different purposes.
     *
     * A `Post` object, for example, would `push()` itself onto the entity stack
     * to retrieve a `User` instance for its `Author` property. The `Post` has a
     * reference to a `User`, but is not its parent because the reference is not
     * hierarchical. (Also, only a `User` entity can be the parent or child of
     * another `User` entity.)
     *
     * A `Staff` object, however, would `push()` itself onto the entity stack to
     * retrieve `Staff` instances for its `DirectReports` property, **and** pass
     * itself to `withParent()` as the parent (a.k.a. manager) of those `Staff`.
     *
     * @return $this
     */
    public function push(IProvidable $entity);

    /**
     * Set the context's container
     *
     * @return $this
     */
    public function withContainer(IContainer $container);

    /**
     * Set the parent of IHierarchy entities instantiated within the context
     *
     * @see IProviderContext::push()
     * @return $this
     */
    public function withParent(?IHierarchy $parent);

    /**
     * Propagate the current payload's array key conformity
     *
     * @param int $conformity One of the {@see ArrayKeyConformity} values. Use
     * `COMPLETE` wherever possible to improve performance.
     * @psalm-param ArrayKeyConformity::* $conformity
     * @return $this
     */
    public function withConformity(int $conformity);

    /**
     * Get the value most recently passed to set($key)
     *
     */
    public function get(string $key);

    /**
     * Get the entities responsible for propagating this context
     *
     * @return IProvidable[]
     */
    public function getStack(): array;

    /**
     * Get the value most recently passed to withParent()
     *
     */
    public function getParent(): ?IHierarchy;

    /**
     * Get the current payload's array key conformity
     *
     * @psalm-return ArrayKeyConformity::*
     */
    public function getConformity(): int;
}
