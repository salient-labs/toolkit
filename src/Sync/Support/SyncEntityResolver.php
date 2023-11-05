<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Contract\ISyncEntityResolver;
use Lkrms\Sync\Exception\SyncFilterPolicyViolationException;

/**
 * Resolves a name to an entity
 *
 * @template TEntity of ISyncEntity
 * @implements ISyncEntityResolver<TEntity>
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

    public function getByName(string $name, ?float &$uncertainty = null): ?ISyncEntity
    {
        $match = null;
        foreach ([[[$this->NameProperty => $name]], []] as $args) {
            try {
                $match = $this
                    ->EntityProvider
                    ->getList(...$args)
                    ->nextWithValue($this->NameProperty, $name);
                break;
            } catch (SyncFilterPolicyViolationException $ex) {
                $match = false;
                continue;
            }
        }

        if ($match === false) {
            $uncertainty = null;
            return null;
        }

        $uncertainty = 0.0;
        return $match;
    }
}
