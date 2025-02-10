<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\HasContextualBindings;
use Salient\Contract\Container\HasServices;
use Salient\Contract\Core\Pipeline\PipelineInterface;
use Salient\Contract\Sync\FilterPolicy;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\Provider\AbstractProvider;
use Salient\Core\Pipeline;
use Salient\Sync\Exception\FilterPolicyViolationException;
use Salient\Sync\Exception\SyncEntityRecursionException;
use Salient\Sync\Reflection\SyncProviderReflection;
use Salient\Sync\Support\SyncContext;
use Salient\Sync\Support\SyncEntityProvider;
use Salient\Sync\Support\SyncIntrospector;
use Salient\Sync\Support\SyncPipelineArgument;
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
    public function getContext(): SyncContextInterface
    {
        return new SyncContext($this, $this->App);
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
     *                     $ctx,
     *                 )
     *         );
     *     }
     * }
     * ```
     *
     * @template T of SyncEntityInterface
     * @template TOutput of iterable<T>|T
     *
     * @param Closure(): TOutput $operation
     * @return TOutput
     */
    protected function run(SyncContextInterface $context, Closure $operation)
    {
        return $this->filterOperationOutput(
            $context,
            $this->runOperation($context, $operation),
        );
    }

    /**
     * Get a new pipeline for mapping provider data to entities
     *
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $entity
     * @return PipelineInterface<mixed[],T,SyncPipelineArgument>
     */
    protected function pipelineFrom(string $entity): PipelineInterface
    {
        /** @var PipelineInterface<mixed[],T,SyncPipelineArgument> */
        return Pipeline::create();
    }

    /**
     * Get a new pipeline for mapping entities to provider data
     *
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $entity
     * @return PipelineInterface<T,mixed[],SyncPipelineArgument>
     */
    protected function pipelineTo(string $entity): PipelineInterface
    {
        /** @var PipelineInterface<T,mixed[],SyncPipelineArgument> */
        return Pipeline::create();
    }

    /**
     * @inheritDoc
     */
    final public static function getServices(): array
    {
        $provider = new SyncProviderReflection(static::class);
        return $provider->getSyncProviderInterfaces();
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
            if ($context->recursionDetected()) {
                throw new SyncEntityRecursionException(sprintf(
                    'Circular reference detected: %s',
                    $context->getLastEntity()->getUri($this->Store),
                ));
            }
            $container = $context->getContainer();
        } else {
            $container = $this->App;
        }

        $container = $container->inContextOf(static::class);
        $context = ($context ?? $this->getContext())->withContainer($container);

        return $container->get(
            SyncEntityProvider::class,
            ['entity' => $entity, 'provider' => $this, 'context' => $context],
        );
    }

    /**
     * @template T
     * @template TOutput of iterable<T>|T
     *
     * @param Closure(): TOutput $operation
     * @return TOutput
     */
    final public function runOperation(SyncContextInterface $context, Closure $operation)
    {
        if (!$context->hasOperation()) {
            throw new LogicException('Context has no operation');
        }

        if ($context->hasFilter()) {
            $policy = $context->getProvider()->getFilterPolicy()
                ?? FilterPolicy::THROW_EXCEPTION;

            switch ($policy) {
                case FilterPolicy::IGNORE:
                    break;

                case FilterPolicy::THROW_EXCEPTION:
                    throw new FilterPolicyViolationException(
                        $this,
                        $context->getEntityType(),
                        $context->getFilters(),
                    );

                case FilterPolicy::RETURN_EMPTY:
                    /** @var TOutput */
                    return SyncUtil::isListOperation($context->getOperation())
                        ? []
                        : null;

                case FilterPolicy::FILTER:
                    break;

                default:
                    throw new LogicException(sprintf(
                        'Invalid unclaimed filter policy: %d',
                        $policy,
                    ));
            }
        }

        return $operation();
    }

    /**
     * @inheritDoc
     */
    final public function filterOperationOutput(SyncContextInterface $context, $output)
    {
        if (!$context->hasOperation()) {
            throw new LogicException('Context has no operation');
        }

        if (
            $context->hasFilter()
            && $context->getProvider()->getFilterPolicy() === FilterPolicy::FILTER
        ) {
            throw new LogicException('Unclaimed filter policy not implemented');
        }

        return $output;
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
