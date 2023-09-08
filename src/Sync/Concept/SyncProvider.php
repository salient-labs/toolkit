<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Lkrms\Container\Container;
use Lkrms\Contract\IPipeline;
use Lkrms\Contract\IService;
use Lkrms\Support\DateFormatter;
use Lkrms\Support\Pipeline;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncEntityProvider;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncSerializeRulesBuilder as SerializeRulesBuilder;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Utility\Env;
use Closure;
use LogicException;

/**
 * Base class for providers that sync entities to and from third-party backends
 *
 */
abstract class SyncProvider implements ISyncProvider, IService
{
    /**
     * Get a DateFormatter to work with the backend's date format and timezone
     *
     * The {@see DateFormatter} returned by this method is cached for the
     * lifetime of the {@see SyncProvider} instance.
     */
    abstract protected function getDateFormatter(): DateFormatter;

    /**
     * Get a dependency subtitution map for the class
     *
     * {@inheritDoc}
     *
     * Bind any {@see ISyncEntity} classes customised for this provider to their
     * generic parent classes by overriding this method, e.g.:
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
     *
     */
    public static function getContextualBindings(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function description(): ?string
    {
        return null;
    }

    /**
     * @var Container
     */
    protected $App;

    /**
     * @var Env
     */
    protected $Env;

    /**
     * @var SyncStore
     */
    protected $Store;

    /**
     * @var int|null
     */
    private $Id;

    /**
     * @var DateFormatter|null
     */
    private $DateFormatter;

    /**
     * @var array<string,Closure>
     */
    private $MagicMethodClosures = [];

    public function __construct(Container $app, Env $environment, SyncStore $store)
    {
        $this->App = $app;
        $this->Env = $environment;
        $this->Store = $store;

        $this->Store->provider($this);
    }

    final public function setProviderId(int $providerId)
    {
        $this->Id = $providerId;

        return $this;
    }

    final public function app(): Container
    {
        return $this->App;
    }

    final public function container(): Container
    {
        return $this->App;
    }

    final public function env(): Env
    {
        return $this->Env;
    }

    final public function store(): SyncStore
    {
        return $this->Store;
    }

    /**
     * Get a new pipeline bound to the provider's container
     *
     */
    final protected function pipeline(): IPipeline
    {
        return Pipeline::create($this->App);
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
    final public function dateFormatter(): DateFormatter
    {
        return $this->DateFormatter
            ?? ($this->DateFormatter = $this->getDateFormatter());
    }

    final public static function getServices(): array
    {
        return SyncIntrospector::get(static::class)->getSyncProviderInterfaces();
    }

    /**
     * @template TEntity of ISyncEntity
     * @param class-string<TEntity> $entity
     * @return SyncEntityProvider<TEntity,static>
     */
    final public function with(string $entity, $context = null): SyncEntityProvider
    {
        $this->Store->entityType($entity);

        $container = ($context instanceof ISyncContext
            ? $context->container()
            : ($context ?: $this->container()))->inContextOf(static::class);
        $context = $context instanceof ISyncContext
            ? $context->withContainer($container)
            : $container->get(SyncContext::class);

        return $container->get(
            SyncEntityProvider::class,
            [$entity, $this, $this->getDefinition($entity), $context]
        );
    }

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

    final public function getProviderId(): ?int
    {
        return $this->Id;
    }
}
