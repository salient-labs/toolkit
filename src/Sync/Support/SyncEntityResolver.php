<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Support\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Contract\ISyncEntityResolver;
use Lkrms\Utility\Convert;

/**
 * Resolves names to entities
 *
 * @template TEntity of ISyncEntity
 */
final class SyncEntityResolver implements ISyncEntityResolver
{
    /**
     * @var ISyncEntityProvider<TEntity>
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
        $this->NameProperty = $nameProperty;
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
