<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Sync\Exception\FilterPolicyViolationExceptionInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncEntityProviderInterface;
use Salient\Contract\Sync\SyncEntityResolverInterface;

/**
 * Resolves a name to an entity by iterating over entities filtered by name
 *
 * Entities are retrieved every time {@see SyncEntityResolver::getByName()} is
 * called. If a request fails because the provider is unable to filter entities
 * by name, a fallback request for every entity is made.
 *
 * @api
 *
 * @template TEntity of SyncEntityInterface
 *
 * @implements SyncEntityResolverInterface<TEntity>
 */
final class SyncEntityResolver implements SyncEntityResolverInterface
{
    /** @var SyncEntityProviderInterface<TEntity> */
    private SyncEntityProviderInterface $EntityProvider;
    private string $NameProperty;

    /**
     * @api
     *
     * @param SyncEntityProviderInterface<TEntity> $entityProvider
     */
    public function __construct(
        SyncEntityProviderInterface $entityProvider,
        string $nameProperty
    ) {
        $this->EntityProvider = $entityProvider;
        $this->NameProperty = $nameProperty;
    }

    /**
     * @inheritDoc
     */
    public function getByName(string $name, ?float &$uncertainty = null): ?SyncEntityInterface
    {
        try {
            $entity = $this->doGetByName($name, [$this->NameProperty => $name]);
        } catch (FilterPolicyViolationExceptionInterface $ex) {
            $entity = $this->doGetByName($name);
        }

        $uncertainty = $entity ? 0.0 : null;
        return $entity;
    }

    /**
     * @param mixed ...$args
     * @return TEntity|null
     */
    private function doGetByName(string $name, ...$args): ?SyncEntityInterface
    {
        return $this
            ->EntityProvider
            ->getList(...$args)
            ->nextWithValue($this->NameProperty, $name, true);
    }
}
