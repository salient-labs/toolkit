<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Catalog\Sync\FilterPolicy;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Utility\Json;

/**
 * Thrown when there are unclaimed sync operation filters
 *
 * @see FilterPolicy
 */
class SyncFilterPolicyViolationException extends AbstractSyncException
{
    /**
     * @var array<string,mixed>
     */
    protected array $Unclaimed;

    /**
     * @param class-string<SyncEntityInterface> $entity
     * @param array<string,mixed> $unclaimed
     */
    public function __construct(SyncProviderInterface $provider, string $entity, array $unclaimed)
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
            'Unclaimed' => Json::prettyPrint($this->Unclaimed),
        ];
    }
}
