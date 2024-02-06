<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Lkrms\Concept\Provider;
use Lkrms\Container\Contract\HasContextualBindings;
use Lkrms\Container\Contract\HasServices;
use Lkrms\Container\ContainerInterface;
use Lkrms\Contract\IPipeline;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Support\Pipeline;
use Lkrms\Sync\Catalog\SyncOperation as OP;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncEntityProvider;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncSerializeRulesBuilder as SerializeRulesBuilder;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Str;
use Closure;
use LogicException;

/**
 * Base class for providers that sync entities to and from third-party backends
 */
abstract class SyncProvider extends Provider implements ISyncProvider, HasServices, HasContextualBindings
{
    /**
     * Get a dependency substitution map for the provider
     *
     * {@inheritDoc}
     *
     * Override this method to bind any {@see ISyncEntity} classes customised
     * for the provider to their generic parent classes, e.g.:
     *
     * ```php
     * <?php
     * public static function getContextualBindings(): array
     * {
     *     return [
     *         Post::class => CustomPost::class,
     *         User::class => CustomUser::class,
     *     ];
     * }
     * ```
     */
    public static function getContextualBindings(): array
    {
        return [];
    }

    /**
     * @var SyncStore
     */
    protected $Store;

    /**
     * @var int|null
     */
    private $Id;

    /**
     * @var array<string,Closure>
     */
    private $MagicMethodClosures = [];

    /**
     * Creates a new SyncProvider object
     *
     * Creating an instance of the provider registers it with the entity store
     * injected by the container.
     */
    public function __construct(ContainerInterface $app, SyncStore $store)
    {
        parent::__construct($app);
        $this->Store = $store;
        $this->Store->provider($this);
    }

    /**
     * @inheritDoc
     */
    public function getContext(?ContainerInterface $container = null): SyncContext
    {
        if (!$container) {
            $container = $this->App;
        }

        return $container->get(SyncContext::class, [$this]);
    }

    /**
     * @inheritDoc
     */
    public function getFilterPolicy(): ?int
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function isValidIdentifier($id, string $entity): bool
    {
        if (
            is_int($id) ||
            Pcre::match(Regex::anchorAndDelimit(Regex::MONGODB_OBJECTID), $id) ||
            Pcre::match(Regex::anchorAndDelimit(Regex::UUID), $id)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    final public function store(): SyncStore
    {
        return $this->Store;
    }

    /**
     * @inheritDoc
     */
    final public function setProviderId(int $providerId)
    {
        $this->Id = $providerId;
        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function getProviderId(): ?int
    {
        return $this->Id
            ?? $this->Store->getProviderId($this);
    }

    /**
     * Perform a sync operation if its context is valid
     *
     * Providers where sync operations are performed by declared methods should
     * use this method to ensure filter policy violations are caught and to take
     * advantage of other safety checks that may be added in the future.
     *
     * Example:
     *
     * ```php
     * <?php
     * class Provider extends HttpSyncProvider
     * {
     *     public function getEntities(ISyncContext $ctx): iterable
     *     {
     *         // Claim filter values
     *         $start = $ctx->claimFilter('start_date');
     *         $end = $ctx->claimFilter('end_date');
     *
     *         return $this->run(
     *             $ctx,
     *             fn(): iterable =>
     *                 Entity::provide(
     *                     $this->getCurler('/entities')->getP([
     *                         'from' => $start,
     *                         'to' => $end,
     *                     ]),
     *                     $this,
     *                     $ctx,
     *                 )
     *         );
     *     }
     * }
     * ```
     *
     * @template T of iterable<ISyncEntity>|ISyncEntity
     *
     * @param callable(): T $operation
     * @return T
     */
    protected function run(ISyncContext $context, callable $operation)
    {
        $context->applyFilterPolicy($returnEmpty, $empty);
        if ($returnEmpty) {
            return $empty;
        }

        return $operation();
    }

    /**
     * Get a new pipeline for mapping provider data to entities
     *
     * @template T of ISyncEntity
     *
     * @param class-string<T> $entity
     * @return IPipeline<mixed[],T,array{0:OP::*,1:ISyncContext,2?:int|string|T|T[]|null,...}>
     */
    protected function pipelineFrom(string $entity): IPipeline
    {
        return Pipeline::create($this->App);
    }

    /**
     * Get a new pipeline for mapping entities to provider data
     *
     * @template T of ISyncEntity
     *
     * @param class-string<T> $entity
     * @return IPipeline<T,mixed[],array{0:OP::*,1:ISyncContext,2?:int|string|T|T[]|null,...}>
     */
    protected function pipelineTo(string $entity): IPipeline
    {
        return Pipeline::create($this->App);
    }

    /**
     * Use the provider's container to get a serialization rules builder
     * for an entity
     *
     * @template T of ISyncEntity
     *
     * @param class-string<T> $entity
     * @return SerializeRulesBuilder<T>
     */
    final protected function buildSerializeRules(string $entity): SerializeRulesBuilder
    {
        return SerializeRulesBuilder::build($this->App)
            ->entity($entity);
    }

    /**
     * @inheritDoc
     */
    final public static function getServices(): array
    {
        return SyncIntrospector::get(static::class)->getSyncProviderInterfaces();
    }

    /**
     * @template TEntity of ISyncEntity
     *
     * @param class-string<TEntity> $entity
     * @return SyncEntityProvider<TEntity,static>
     */
    final public function with(string $entity, $context = null): SyncEntityProvider
    {
        /** @var ContainerInterface */
        $container = $context instanceof ISyncContext
            ? $context->container()
            : ($context ?: $this->App);
        $container = $container->inContextOf(static::class);

        $context = $context instanceof ISyncContext
            ? $context->withContainer($container)
            : $this->getContext($container);

        return $container->get(
            SyncEntityProvider::class,
            [$entity, $this, $this->getDefinition($entity), $context]
        );
    }

    /**
     * @param mixed[] $arguments
     * @return mixed
     */
    final public function __call(string $name, array $arguments)
    {
        if (($closure = $this->MagicMethodClosures[$name = Str::lower($name)] ?? false) === false) {
            $closure = SyncIntrospector::get(static::class)->getMagicSyncOperationClosure($name, $this);
            $this->MagicMethodClosures[$name] = $closure;
        }
        if ($closure) {
            return $closure(...$arguments);
        }

        throw new LogicException('Call to undefined method: ' . static::class . "::$name()");
    }
}
