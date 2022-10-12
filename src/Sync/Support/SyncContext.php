<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Facade\Convert;
use Lkrms\Facade\Test;
use Lkrms\Support\ProvidableContext;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncContext;

/**
 * The context within which a SyncEntity is instantiated
 *
 */
final class SyncContext extends ProvidableContext implements ISyncContext
{
    /**
     * @var array|null
     */
    private $Filter;

    public function withArgs(int $operation, ...$args)
    {
        array_shift($args);

        // READ_LIST is the only operation with no mandatory argument after
        // `SyncContext $ctx`
        if ($operation !== SyncOperation::READ_LIST)
        {
            array_shift($args);
        }

        if (empty($args))
        {
            return $this->maybeMutate("Filter", []);
        }

        if (is_array($args[0]) && count($args) === 1)
        {
            return $this->maybeMutate("Filter", array_combine(
                array_map(
                    fn($key) => preg_match('/[^[:alnum:]_-]/', $key) ? $key : Convert::toSnakeCase($key),
                    array_keys($args[0])
                ),
                array_map(
                    fn($value) => $value instanceof SyncEntity ? $value->Id : $value,
                    $args[0]
                )
            ));
        }

        if (Test::isArrayOfIntOrString($args))
        {
            return $this->maybeMutate("Filter", ["id" => $args]);
        }

        if (Test::isArrayOf($args, SyncEntity::class))
        {
            return $this->maybeMutate("Filter", array_merge_recursive(...array_map(
                fn(SyncEntity $entity): array => [
                    Convert::toSnakeCase(Convert::classToBasename(
                        $entity->providable() ?: get_class($entity)
                    )) => [$entity->Id]
                ],
                $args
            )));
        }

        return $this->maybeMutate("Filter", null);
    }

    public function getFilter(): ?array
    {
        return $this->Filter;
    }

}
