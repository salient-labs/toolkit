<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\Exception\FilterPolicyViolationExceptionInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Utility\Format;

/**
 * @internal
 */
class FilterPolicyViolationException extends SyncException implements FilterPolicyViolationExceptionInterface
{
    /** @var array<string,mixed> */
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
            $this->getProviderName($provider),
            $entity,
            implode(', ', array_keys($unclaimed)),
        ));
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(): array
    {
        return [
            'Unclaimed' => Format::array($this->Unclaimed),
        ];
    }
}
