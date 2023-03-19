<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Contract\IIterable;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Test;
use Lkrms\Support\ProviderContext;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use UnexpectedValueException;

/**
 * The context within which a sync entity is instantiated
 *
 * @template TList of array|IIterable
 * @implements ISyncContext<TList>
 */
final class SyncContext extends ProviderContext implements ISyncContext
{
    protected bool $IteratorToArray = false;

    protected array $Filter = [];

    /**
     * @return SyncContext<array>
     */
    public function withArrays()
    {
        /** @var SyncContext<array> */
        $instance = $this->withPropertyValue('IteratorToArray', true);

        return $instance;
    }

    /**
     * @return SyncContext<IIterable>
     */
    public function withIterators()
    {
        /** @var SyncContext<IIterable> */
        $instance = $this->withPropertyValue('IteratorToArray', false);

        return $instance;
    }

    public function withArgs(int $operation, ...$args)
    {
        // READ_LIST is the only operation with no mandatory argument after
        // `SyncContext $ctx`
        if ($operation !== SyncOperation::READ_LIST) {
            array_shift($args);
        }

        if (empty($args)) {
            return $this->withPropertyValue('Filter', []);
        }

        if (is_array($args[0]) && count($args) === 1) {
            return $this->withPropertyValue('Filter', array_combine(
                array_map(
                    fn($key) =>
                        preg_match('/[^[:alnum:]_-]/', $key) ? $key : Convert::toSnakeCase($key),
                    array_keys($args[0])
                ),
                array_map(
                    fn($value) =>
                        $value instanceof ISyncEntity ? $value->id() : $value,
                    $args[0]
                )
            ));
        }

        if (Test::isArrayOfIntOrString($args)) {
            return $this->withPropertyValue('Filter', ['id' => $args]);
        }

        if (Test::isArrayOf($args, ISyncEntity::class)) {
            return $this->withPropertyValue(
                'Filter',
                array_merge_recursive(
                    ...array_map(
                        fn(ISyncEntity $entity): array =>
                            [Convert::toSnakeCase(Convert::classToBasename($entity->service())) =>
                                [$entity->id()]],
                        $args
                    )
                )
            );
        }

        throw new UnexpectedValueException('Filter signature not recognised');
    }

    public function getIteratorToArray(): bool
    {
        return $this->IteratorToArray;
    }

    public function getFilter(): array
    {
        return $this->Filter;
    }

    public function claimFilterValue(string $key)
    {
        if (array_key_exists($key, $this->Filter)) {
            $value = $this->Filter[$key];
            unset($this->Filter[$key]);

            return $value;
        }

        return null;
    }
}
