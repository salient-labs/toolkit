<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Lkrms\Concept\Provider;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Contract\IService;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Support\Pipeline;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncEntityProvider;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncSerializeRulesBuilder as SerializeRulesBuilder;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Utility\Env;
use Lkrms\Utility\Pcre;
use Closure;
use LogicException;

/**
 * Base class for providers that sync entities to and from third-party backends
 */
abstract class SyncProvider extends Provider implements ISyncProvider, IService
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
     * Creates a new provider object
     *
     * Creating an instance of the provider registers it with the entity store
     * injected by the container.
     */
    public function __construct(IContainer $app, Env $env, SyncStore $store)
    {
        parent::__construct($app, $env);
        $this->Store = $store;
        $this->Store->provider($this);
    }

    /**
     * @inheritDoc
     */
    public function getContext(?IContainer $container = null): SyncContext
    {
        if (!$container) {
            $container = $this->App;
        }

        return $container->get(SyncContext::class, [$this]);
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
     * Get a new pipeline bound to the provider's container
     *
     * @return IPipeline<mixed,mixed,mixed>
     */
    final protected function pipeline(): IPipeline
    {
        return Pipeline::create($this->App);
    }

    /**
     * Wrap a new pipeline around a callback
     *
     * @param (callable(mixed $payload, IPipeline<mixed,mixed,mixed> $pipeline, mixed $arg): mixed) $callback
     * @return IPipeline<mixed,mixed,mixed>
     */
    final protected function callbackPipeline(callable $callback): IPipeline
    {
        return Pipeline::create($this->App)->throughCallback($callback);
    }

    /**
     * Use the provider's container to get a serialization rules builder
     * for an entity
     *
     * @template T of ISyncEntity
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
     * @inheritDoc
     *
     * @template TEntity of ISyncEntity
     * @param class-string<TEntity> $entity
     * @return SyncEntityProvider<TEntity,static>
     */
    final public function with(string $entity, $context = null): SyncEntityProvider
    {
        /** @var IContainer */
        $container = $context instanceof ISyncContext
            ? $context->container()
            : ($context ?? $this->App);
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
        if (($closure = $this->MagicMethodClosures[$name = strtolower($name)] ?? false) === false) {
            $closure = SyncIntrospector::get(static::class)->getMagicSyncOperationClosure($name, $this);
            $this->MagicMethodClosures[$name] = $closure;
        }
        if ($closure) {
            return $closure(...$arguments);
        }

        throw new LogicException('Call to undefined method: ' . static::class . "::$name()");
    }
}
