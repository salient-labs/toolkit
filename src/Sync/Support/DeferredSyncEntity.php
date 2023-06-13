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
 * @property-read string $Entity
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
     * @var string
     */
    protected $Entity;

    /**
     * @var int|string
     */
    protected $Deferred;

    private $Replace;

    private function __construct(ISyncProvider $provider, ISyncContext $context, string $entity, $deferred, &$replace)
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
        return $this->Provider->store()->getEntityTypeUri($this->Entity, $compact)
            ?: '/' . str_replace('\\', '/', ltrim($this->Entity, '\\'));
    }

    public function replace(ISyncEntity $entity): void
    {
        $this->Replace = $entity;
        unset($this->Replace);
    }

    /**
     * @param int|string|int[]|string[] $deferred An entity ID or list thereof.
     * @param mixed $replace A reference to the variable, property or array
     * element to replace when the entity or list is resolved. Do not assign
     * anything else to it after calling this method.
     */
    public static function defer(
        ISyncProvider $provider,
        ISyncContext $context,
        string $entity,
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
     * @param int[]|string[] $deferredList A list of entity IDs.
     */
    public static function deferList(
        ISyncProvider $provider,
        ISyncContext $context,
        string $entity,
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
