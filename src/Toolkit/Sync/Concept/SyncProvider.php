<?php declare(strict_types=1);

namespace Salient\Sync\Concept;

use Salient\Container\Contract\HasContextualBindings;
use Salient\Container\Contract\HasServices;
use Salient\Container\ContainerInterface;
use Salient\Core\Catalog\Regex;
use Salient\Core\Contract\PipelineInterface;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;
use Salient\Core\AbstractProvider;
use Salient\Core\Pipeline;
use Salient\Sync\Catalog\SyncOperation as OP;
use Salient\Sync\Contract\ISyncContext;
use Salient\Sync\Contract\ISyncEntity;
use Salient\Sync\Contract\ISyncProvider;
use Salient\Sync\Support\SyncContext;
use Salient\Sync\Support\SyncEntityProvider;
use Salient\Sync\Support\SyncIntrospector;
use Salient\Sync\Support\SyncSerializeRulesBuilder as SerializeRulesBuilder;
use Salient\Sync\Support\SyncStore;
use Closure;
use LogicException;

/**
 * Base class for providers that sync entities to and from third-party backends
 */
abstract class SyncProvider extends AbstractProvider implements ISyncProvider, HasServices, HasContextualBindings
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
    public function getContext(?ContainerInterface $container = null): ISyncContext
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
            Pcre::match(Pcre::delimit('^' . Regex::MONGODB_OBJECTID . '$', '/'), $id) ||
            Pcre::match(Pcre::delimit('^' . Regex::UUID . '$', '/'), $id)
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
     * @return PipelineInterface<mixed[],T,array{0:OP::*,1:ISyncContext,2?:int|string|T|T[]|null,...}>
     */
    protected function pipelineFrom(string $entity): PipelineInterface
    {
        return Pipeline::create($this->App);
    }

    /**
     * Get a new pipeline for mapping entities to provider data
     *
     * @template T of ISyncEntity
     *
     * @param class-string<T> $entity
     * @return PipelineInterface<T,mixed[],array{0:OP::*,1:ISyncContext,2?:int|string|T|T[]|null,...}>
     */
    protected function pipelineTo(string $entity): PipelineInterface
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
        if ($context instanceof ISyncContext) {
            $context->maybeThrowRecursionException();
            $container = $context->container();
        } else {
            /** @var ContainerInterface */
            $container = $context ?? $this->App;
        }

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
