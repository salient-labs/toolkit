<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\HasContextualBindings;
use Salient\Contract\Container\HasServices;
use Salient\Contract\Core\Pipeline\PipelineInterface;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation as OP;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\AbstractProvider;
use Salient\Core\Pipeline;
use Salient\Sync\Support\SyncContext;
use Salient\Sync\Support\SyncEntityProvider;
use Salient\Sync\Support\SyncIntrospector;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Closure;
use LogicException;

/**
 * Base class for providers that sync entities to and from third-party backends
 */
abstract class AbstractSyncProvider extends AbstractProvider implements
    SyncProviderInterface,
    HasServices,
    HasContextualBindings
{
    /**
     * Get a dependency substitution map for the provider
     *
     * {@inheritDoc}
     *
     * Override this method to bind any {@see SyncEntityInterface} classes
     * customised for the provider to their generic parent classes, e.g.:
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

    protected SyncStoreInterface $Store;
    private int $Id;
    /** @var array<string,Closure|null> */
    private array $MagicMethodClosures = [];

    /**
     * Creates a new sync provider object
     *
     * Creating an instance of the provider registers it with the entity store
     * injected by the container.
     */
    public function __construct(ContainerInterface $app, SyncStoreInterface $store)
    {
        parent::__construct($app);

        $this->Store = $store;
        $this->Store->registerProvider($this);
    }

    /**
     * @inheritDoc
     */
    public function getContext(?ContainerInterface $container = null): SyncContextInterface
    {
        if (!$container) {
            $container = $this->App;
        }

        return $container->get(SyncContext::class, ['provider' => $this]);
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
        return is_int($id)
            || Regex::match(Regex::delimit('^' . Regex::MONGODB_OBJECTID . '$', '/'), $id)
            || Regex::match(Regex::delimit('^' . Regex::UUID . '$', '/'), $id);
    }

    /**
     * @inheritDoc
     */
    final public function getStore(): SyncStoreInterface
    {
        return $this->Store;
    }

    /**
     * @inheritDoc
     */
    final public function getProviderId(): int
    {
        return $this->Id ??= $this->Store->getProviderId($this);
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
     *     public function getEntities(SyncContextInterface $ctx): iterable
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
     * @template T of iterable<SyncEntityInterface>|SyncEntityInterface
     *
     * @param callable(): T $operation
     * @return T
     */
    protected function run(SyncContextInterface $context, callable $operation)
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
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $entity
     * @return PipelineInterface<mixed[],T,array{0:OP::*,1:SyncContextInterface,2?:int|string|T|T[]|null,...}>
     */
    protected function pipelineFrom(string $entity): PipelineInterface
    {
        /** @var PipelineInterface<mixed[],T,array{0:OP::*,1:SyncContextInterface,2?:int|string|T|T[]|null,...}> */
        return Pipeline::create($this->App);
    }

    /**
     * Get a new pipeline for mapping entities to provider data
     *
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $entity
     * @return PipelineInterface<T,mixed[],array{0:OP::*,1:SyncContextInterface,2?:int|string|T|T[]|null,...}>
     */
    protected function pipelineTo(string $entity): PipelineInterface
    {
        /** @var PipelineInterface<T,mixed[],array{0:OP::*,1:SyncContextInterface,2?:int|string|T|T[]|null,...}> */
        return Pipeline::create($this->App);
    }

    /**
     * @inheritDoc
     */
    final public static function getServices(): array
    {
        return SyncIntrospector::get(static::class)->getSyncProviderInterfaces();
    }

    /**
     * @template TEntity of SyncEntityInterface
     *
     * @param class-string<TEntity> $entity
     * @return SyncEntityProvider<TEntity,static>
     */
    final public function with(string $entity, ?SyncContextInterface $context = null): SyncEntityProvider
    {
        if ($context) {
            $context->maybeThrowRecursionException();
            $container = $context->getContainer();
        } else {
            $container = $this->App;
        }

        $container = $container->inContextOf(static::class);
        $context = $context
            ? $context->withContainer($container)
            : $this->getContext($container);

        return $container->get(
            SyncEntityProvider::class,
            ['entity' => $entity, 'provider' => $this, 'context' => $context],
        );
    }

    /**
     * @param mixed[] $arguments
     * @return mixed
     */
    final public function __call(string $name, array $arguments)
    {
        $name = Str::lower($name);
        if (array_key_exists($name, $this->MagicMethodClosures)) {
            $closure = $this->MagicMethodClosures[$name];
        } else {
            $closure = SyncIntrospector::get(static::class)->getMagicSyncOperationClosure($name, $this);
            $this->MagicMethodClosures[$name] = $closure;
        }

        if ($closure) {
            return $closure(...$arguments);
        }

        throw new LogicException('Call to undefined method: ' . static::class . "::$name()");
    }
}
