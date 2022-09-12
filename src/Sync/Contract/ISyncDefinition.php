<?php

declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Closure;
use Lkrms\Contract\IPipeline;

/**
 * Provides access to an ISyncProvider's implementation of sync operations for
 * an entity
 *
 */
interface ISyncDefinition
{
    /**
     * Get the name of the entity class
     *
     */
    public function getSyncEntity(): string;

    /**
     * Get the provider servicing the entity
     *
     */
    public function getSyncProvider(): ISyncProvider;

    /**
     * Return a closure that uses the provider to perform a sync operation on
     * the entity
     *
     * If the sync operation is not supported, return `null`.
     *
     * @see \Lkrms\Sync\SyncOperation
     */
    public function getSyncOperationClosure(int $operation): ?Closure;

    /**
     * Return a pipeline that converts data received from the provider to an
     * associative array from which the entity's class can be instantiated
     *
     * If the entity is not supported or conversion is not required, return
     * `null`.
     *
     */
    public function getDataToEntityPipeline(): ?IPipeline;

    /**
     * Return a pipeline that converts a serialized instance of the entity to
     * data compatible with the provider
     *
     * If the entity is not supported or conversion is not required, return
     * `null`.
     *
     */
    public function getEntityToDataPipeline(): ?IPipeline;

}
