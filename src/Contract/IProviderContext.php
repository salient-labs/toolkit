<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\Catalog\ArrayKeyConformity;

/**
 * The context within which an entity is instantiated by a provider
 *
 * @extends ReturnsContainer<IContainer>
 * @extends ReturnsProvider<IProvider>
 */
interface IProviderContext extends IImmutable, ReturnsContainer, ReturnsProvider
{
    /**
     * Apply a container to the context
     *
     * @return $this
     */
    public function withContainer(IContainer $container);

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
     * @param IProvidable<IProvider,IProviderContext> $entity
     * @return $this
     */
    public function push(IProvidable $entity);

    /**
     * Apply a parent entity to the context
     *
     * @see IProviderContext::push()
     *
     * @return $this
     */
    public function withParent(?ITreeable $parent);

    /**
     * Apply the current payload's array key conformity to the context
     *
     * @param ArrayKeyConformity::* $conformity Use
     * {@see ArrayKeyConformity::COMPLETE} wherever possible to improve
     * performance.
     * @return $this
     */
    public function withConformity($conformity);

    /**
     * @inheritDoc
     */
    public function provider(): IProvider;

    /**
     * Get the entities responsible for propagating this context
     *
     * @return IProvidable<IProvider,IProviderContext>[]
     */
    public function stack(): array;

    /**
     * Get the parent entity applied to the context
     */
    public function getParent(): ?ITreeable;

    /**
     * Get the current payload's array key conformity
     *
     * @return ArrayKeyConformity::*
     */
    public function getConformity(): int;
}
