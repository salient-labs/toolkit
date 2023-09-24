<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Sync\Catalog\SyncEntityLinkType as LinkType;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use LogicException;

/**
 * The promise of a sync entity that hasn't been created yet
 *
 * @property-read ISyncProvider $Provider
 * @property-read ISyncContext|null $Context
 * @property-read class-string<ISyncEntity>|null $Entity
 * @property-read int|string $Deferred
 */
final class DeferredSyncEntity implements IReadable
{
    use TFullyReadable;

    /**
     * The provider servicing the entity
     *
     * @var ISyncProvider
     */
    protected $Provider;

    /**
     * The context in which the entity is being serviced
     *
     * @var ISyncContext|null
     */
    protected $Context;

    /**
     * The entity to instantiate, or `null` if the receiving entity is
     * responsible for resolving the deferred entity
     *
     * @var class-string<ISyncEntity>|null
     */
    protected $Entity;

    /**
     * The identifier of the deferred entity
     *
     * @var int|string
     */
    protected $Deferred;

    /**
     * @var ISyncEntity|DeferredSyncEntity|null
     */
    private $Replace;

    /**
     * Creates a new DeferredSyncEntity object
     *
     * @param class-string<ISyncEntity> $entity
     * @param int|string $deferred
     * @param ISyncEntity|DeferredSyncEntity|null $replace
     */
    private function __construct(
        ISyncProvider $provider,
        ?ISyncContext $context,
        string $entity,
        $deferred,
        &$replace
    ) {
        $this->Provider = $provider;
        $this->Context = $context;
        $this->Entity = $entity;
        $this->Deferred = $deferred;
        $this->Replace = &$replace;
        $this->Replace = $this;

        $this->store()
            ->entityType($entity)
            ->deferredEntity($this->Provider->getProviderId(), $entity, $deferred, $this);
    }

    /**
     * Get the deferred entity's canonical location in the form of an array
     *
     * @param LinkType::* $type
     * @return array<string,int|string>
     */
    public function toLink(int $type = LinkType::DEFAULT, bool $compact = true): array
    {
        switch ($type) {
            case LinkType::INTERNAL:
            case LinkType::DEFAULT:
                return [
                    '@type' => $this->typeUri($compact),
                    '@id' => $this->Deferred,
                ];

            case LinkType::COMPACT:
                return [
                    '@id' => $this->uri($compact),
                ];
        }

        throw new LogicException("Invalid link type: $type");
    }

    /**
     * Get the deferred entity's canonical location in the form of a URI
     *
     */
    public function uri(bool $compact = true): string
    {
        return sprintf('%s/%s', $this->typeUri($compact), $this->Deferred);
    }

    private function typeUri(bool $compact): string
    {
        if ($this->Entity === null) {
            return '';
        }
        return $this->store()->getEntityTypeUri($this->Entity, $compact)
            ?: '/' . str_replace('\\', '/', ltrim($this->Entity, '\\'));
    }

    /**
     * Resolve the deferred entity
     *
     */
    public function replace(ISyncEntity $entity): void
    {
        $this->Replace = $entity;
        unset($this->Replace);
    }

    /**
     * Defer creation of an entity
     *
     * @param class-string<ISyncEntity> $entity The entity to instantiate.
     * @param int|string $deferredId The deferred entity's identifier.
     * @param ISyncEntity|DeferredSyncEntity|null $replace Refers to the
     * variable, property or array element to replace when the entity is
     * resolved. Do not assign anything else to it after calling this method.
     */
    public static function defer(
        ISyncProvider $provider,
        ?ISyncContext $context,
        string $entity,
        $deferredId,
        &$replace = null
    ): void {
        new self($provider, $context, $entity, $deferredId, $replace);
    }

    /**
     * Defer creation of a list of entities
     *
     * @param class-string<ISyncEntity> $entity The entity to instantiate.
     * @param int[]|string[] $deferredIds A list of deferred entity identifiers.
     * @param array<ISyncEntity|DeferredSyncEntity>|null $replace Refers to the
     * variable, property or array element to replace when the entities are
     * resolved. Do not assign anything else to it after calling this method.
     */
    public static function deferList(
        ISyncProvider $provider,
        ?ISyncContext $context,
        string $entity,
        array $deferredIds,
        &$replace = null
    ): void {
        $i = -1;
        $list = [];
        foreach ($deferredIds as $deferredId) {
            $list[++$i] = null;
            new self($provider, $context, $entity, $deferredId, $list[$i]);
        }
        $replace = $list;
    }

    protected function store(): SyncStore
    {
        return $this->Provider->store();
    }
}
