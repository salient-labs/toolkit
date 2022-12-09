<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Facade\Convert;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncEntityResolver;
use Lkrms\Sync\Support\SyncEntityProvider;

/**
 * Resolves names to entities
 *
 */
final class SyncEntityResolver implements ISyncEntityResolver
{
    /**
     * @var SyncEntityProvider
     */
    private $EntityProvider;

    /**
     * @var string
     */
    private $NameProperty;

    public function __construct(SyncEntityProvider $entityProvider, string $nameProperty)
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
