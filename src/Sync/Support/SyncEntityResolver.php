<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Contract\IIterable;
use Lkrms\Facade\Convert;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Contract\ISyncEntityResolver;

/**
 * Resolves names to entities
 *
 * @template TEntity of ISyncEntity
 * @template TList of array|IIterable
 */
final class SyncEntityResolver implements ISyncEntityResolver
{
    /**
     * @var ISyncEntityProvider<TEntity,TList>
     */
    private $EntityProvider;

    /**
     * @var string
     */
    private $NameProperty;

    /**
     * @param ISyncEntityProvider<TEntity,TList> $entityProvider
     */
    public function __construct(ISyncEntityProvider $entityProvider, string $nameProperty)
    {
        $this->EntityProvider = $entityProvider;
        $this->NameProperty   = $nameProperty;
    }

    public function getByName(string $name): ?ISyncEntity
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
