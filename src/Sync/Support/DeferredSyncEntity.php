<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Sync\Catalog\SyncSerializeLinkType as SerializeLinkType;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use UnexpectedValueException;

/**
 * @property-read ISyncProvider $Provider
 * @property-read ISyncContext $Context
 * @property-read class-string<ISyncEntity>|array{0:class-string<ISyncEntity>,1:string} $Entity
 * @property-read int|string $Deferred
 */
final class DeferredSyncEntity implements IReadable
{
    use TFullyReadable;

    /**
     * @var ISyncProvider
     */
    protected $Provider;

    /**
     * @var ISyncContext
     */
    protected $Context;

    /**
     * @var class-string<ISyncEntity>|array{0:class-string<ISyncEntity>,1:string}
     */
    protected $Entity;

    /**
     * @var int|string
     */
    protected $Deferred;

    /**
     * @var mixed
     */
    private $Replace;

    /**
     * @param class-string<ISyncEntity>|array{0:class-string<ISyncEntity>,1:string} $entity
     * @param int|string $deferred
     * @param mixed $replace
     */
    private function __construct(ISyncProvider $provider, ISyncContext $context, $entity, $deferred, &$replace)
    {
        $this->Provider = $provider;
        $this->Context = $context;
        $this->Entity = $entity;
        $this->Deferred = $deferred;
        $this->Replace = &$replace;
        $this->Replace = $this;
    }

    /**
     * Get the deferred entity's canonical location as an array
     *
     * @param SerializeLinkType::* $type
     * @return array<string,int|string>
     */
    public function toLink(int $type = SerializeLinkType::DEFAULT, bool $compact = true): array
    {
        switch ($type) {
            case SerializeLinkType::INTERNAL:
            case SerializeLinkType::DEFAULT:
                return [
                    '@type' => $this->typeUri($compact),
                    '@id' => $this->Deferred,
                ];

            case SerializeLinkType::COMPACT:
                return [
                    '@id' => $this->uri($compact),
                ];
        }

        throw new UnexpectedValueException("Invalid link type: $type");
    }

    public function uri(bool $compact = true): string
    {
        return sprintf('%s/%s', $this->typeUri($compact), $this->Deferred);
    }

    private function typeUri(bool $compact): string
    {
        if (is_array($this->Entity)) {
            return ($this->Provider->store()->getEntityTypeUri($this->Entity[0], $compact)
                ?: '/' . str_replace('\\', '/', ltrim($this->Entity[0], '\\'))) . '.' . $this->Entity[1];
        }
        return $this->Provider->store()->getEntityTypeUri($this->Entity, $compact)
            ?: '/' . str_replace('\\', '/', ltrim($this->Entity, '\\'));
    }

    public function replace(ISyncEntity $entity): void
    {
        $this->Replace = $entity;
        unset($this->Replace);
    }

    /**
     * @param class-string<ISyncEntity>|array{0:class-string<ISyncEntity>,1:string} $entity Either the entity to instantiate, or if unknown, an array that specifies the entity and property being deferred.
     * @param int|string|int[]|string[] $deferred An entity ID or list thereof.
     * @param mixed $replace A reference to the variable, property or array
     * element to replace when the entity is resolved. Do not assign anything
     * else to it after calling this method.
     */
    public static function defer(
        ISyncProvider $provider,
        ISyncContext $context,
        $entity,
        $deferred,
        &$replace
    ): void {
        if (is_array($deferred)) {
            self::deferList($provider, $context, $entity, $deferred, $replace);

            return;
        }

        new self($provider, $context, $entity, $deferred, $replace);
    }

    /**
     * @param class-string<ISyncEntity>|array{0:class-string<ISyncEntity>,1:string} $entity Either the entity to instantiate, or if unknown, an array that specifies the entity and property being deferred.
     * @param int[]|string[] $deferredList A list of entity IDs.
     * @param mixed $replace A reference to the variable, property or array
     * element to replace when the list is resolved. Do not assign anything else
     * to it after calling this method.
     */
    public static function deferList(
        ISyncProvider $provider,
        ISyncContext $context,
        $entity,
        array $deferredList,
        &$replace
    ): void {
        $i = 0;
        $list = [];
        foreach ($deferredList as $deferred) {
            $list[$i] = null;
            new self($provider, $context, $entity, $deferred, $list[$i]);
            $i++;
        }

        $replace = $list;
    }
}
