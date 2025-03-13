<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\Exception\SyncEntityNotFoundExceptionInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Utility\Format;
use Throwable;

class SyncEntityNotFoundException extends SyncException implements SyncEntityNotFoundExceptionInterface
{
    /**
     * @param class-string<SyncEntityInterface> $entity
     * @param int|string|array<string,mixed> $idOrFilter
     */
    public function __construct(
        SyncProviderInterface $provider,
        string $entity,
        $idOrFilter,
        ?Throwable $previous = null
    ) {
        $idOrFilter = is_array($idOrFilter)
            ? $idOrFilter
            : ['id' => $idOrFilter];

        parent::__construct(sprintf(
            '%s does not have %s with %s',
            $this->getProviderName($provider),
            $entity,
            Format::array($idOrFilter, '%s = {%s}, ', 0, ', '),
        ), $previous);
    }
}
