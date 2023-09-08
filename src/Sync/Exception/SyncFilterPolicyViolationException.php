<?php declare(strict_types=1);

namespace Lkrms\Sync\Exception;

use Lkrms\Sync\Catalog\SyncFilterPolicy;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * Thrown when there are unclaimed sync operation filter values
 *
 * @see SyncFilterPolicy
 */
class SyncFilterPolicyViolationException extends SyncException
{
    /**
     * @var array<string,mixed>
     */
    protected array $Unclaimed;

    /**
     * @param class-string<ISyncEntity> $entity
     * @param array<string,mixed> $unclaimed
     */
    public function __construct(ISyncProvider $provider, string $entity, array $unclaimed)
    {
        $this->Unclaimed = $unclaimed;

        parent::__construct(sprintf(
            '%s did not claim values from %s filter: %s',
            get_class($provider),
            $entity,
            implode(', ', array_keys($unclaimed)),
        ));
    }

    public function getDetail(): array
    {
        return [
            'Unclaimed' => json_encode($this->Unclaimed),
        ];
    }
}
