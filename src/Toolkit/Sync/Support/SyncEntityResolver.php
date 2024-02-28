<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncEntityProviderInterface;
use Salient\Contract\Sync\SyncEntityResolverInterface;
use Salient\Sync\Exception\SyncFilterPolicyViolationException;

/**
 * Resolves a name to an entity
 *
 * @template TEntity of SyncEntityInterface
 *
 * @implements SyncEntityResolverInterface<TEntity>
 */
final class SyncEntityResolver implements SyncEntityResolverInterface
{
    /**
     * @var SyncEntityProviderInterface<TEntity>
     */
    private $EntityProvider;

    /**
     * @var string
     */
    private $NameProperty;

    /**
     * @param SyncEntityProviderInterface<TEntity> $entityProvider
     */
    public function __construct(SyncEntityProviderInterface $entityProvider, string $nameProperty)
    {
        $this->EntityProvider = $entityProvider;
        $this->NameProperty = $nameProperty;
    }

    public function getByName(string $name, ?float &$uncertainty = null): ?SyncEntityInterface
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
                $match = null;
                continue;
            }
        }

        if ($match === null) {
            $uncertainty = null;
            return null;
        }

        $uncertainty = 0.0;
        return $match;
    }
}
