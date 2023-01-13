<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Facade\Convert;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Contract\ISyncEntityResolver;

/**
 * Resolves names to entities
 *
 * @template TEntity of SyncEntity
 */
final class SyncEntityResolver implements ISyncEntityResolver
{
    /**
     * @var ISyncEntityProvider
     */
    private $EntityProvider;

    /**
     * @var string
     */
    private $NameProperty;

    /**
     * @param ISyncEntityProvider<TEntity> $entityProvider
     */
    public function __construct(ISyncEntityProvider $entityProvider, string $nameProperty)
    {
        $this->EntityProvider = $entityProvider;
        $this->NameProperty   = $nameProperty;
    }

    public function getByName(string $name): ?SyncEntity
    {
        $match = Convert::iterableToItem(
            $this->EntityProvider->getList([$this->NameProperty => $name]),
            $this->NameProperty,
            $name
        );
        if ($match === false) {
            return null;
        }

        return $match;
    }
}
