<?php declare(strict_types=1);

namespace Lkrms\Sync\Command;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\File;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncIntrospector;

/**
 * A generic sync entity retrieval command
 *
 */
final class GetSyncEntities extends AbstractSyncCommand
{
    private ?string $Entity;
    private ?string $EntityId;
    private ?string $Provider;
    private ?bool $Stream;
    private ?bool $Csv;

    /**
     * @var string[]|null
     */
    private ?array $Filter;

    public function description(): string
    {
        return 'Get data from a provider';
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->long('type')
                ->description('The entity type to request')
                ->optionType(CliOptionType::ONE_OF_POSITIONAL)
                ->valueName('entity_type')
                ->allowedValues(array_keys($this->Entities))
                ->required()
                ->bindTo($this->Entity),
            CliOption::build()
                ->long('id')
                ->description('The unique identifier of a particular entity')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueName('entity_id')
                ->bindTo($this->EntityId),
            CliOption::build()
                ->long('provider')
                ->short('p')
                ->description('The provider to request data from')
                ->optionType(CliOptionType::ONE_OF)
                ->valueName('provider')
                ->allowedValues(array_keys($this->EntityProviders))
                ->bindTo($this->Provider),
            CliOption::build()
                ->long('filter')
                ->short('f')
                ->valueName('term=value')
                ->description('Pass a filter to the provider')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->Filter),
            CliOption::build()
                ->long('stream')
                ->short('s')
                ->description('Output a stream of entities')
                ->bindTo($this->Stream),
            CliOption::build()
                ->long('csv')
                ->short('c')
                ->description('Generate CSV output')
                ->bindTo($this->Csv),
        ];
    }

    protected function run(string ...$args)
    {
        Console::registerStderrTarget(true);

        $entity = $this->Entities[$this->Entity];
        $provider = $this->Provider
            ?: array_search(
                $this->App->getName(SyncIntrospector::entityToProvider($entity)),
                $this->Providers
            );
        $filter = Convert::queryToData($this->Filter);

        if (!$provider) {
            throw new CliInvalidArgumentsException('no default provider: ' . $entity);
        }
        /** @var ISyncProvider */
        $provider = $this->App->get($this->Providers[$provider]);

        Console::info(
            'Retrieving from ' . $provider->name() . ':',
            ($this->Store->getEntityTypeUri($entity)
                    ?: '/' . str_replace('\\', '/', ltrim($entity, '\\')))
                . ($this->EntityId === null ? '' : '/' . $this->EntityId)
        );

        $this->App->bindIf(ISyncContext::class, SyncContext::class);
        $context = $this->App->get(ISyncContext::class);

        $result = $this->EntityId !== null
            ? $provider->with($entity, $context)->get($this->EntityId, $filter)
            : ($this->Stream
                ? $provider->with($entity, $context)->getList($filter)
                : $provider->with($entity, $context)->getListA($filter));

        $rules = $entity::buildSerializeRules($this->App)->includeMeta(false);

        if ($this->Csv) {
            if ($this->EntityId !== null) {
                $result = Convert::toList($result, true);
            }

            $tty = stream_isatty(STDOUT);
            File::writeCsv(
                $result,
                STDOUT,
                true,
                null,
                fn(ISyncEntity $entity) => $entity->toArrayWith($rules),
                $count,
                $tty ? PHP_EOL : "\r\n",
                $tty ? false : true,
                $tty ? false : true
            );
        } elseif (!is_iterable($result) || !$this->Stream) {
            $result = Convert::toList($result, true);
            /** @var ISyncEntity $entity */
            foreach ($result as &$entity) {
                $entity = $entity->toArrayWith($rules);
            }
            $count = count($result);
            if ($this->EntityId !== null) {
                $result = array_shift($result);
            }

            echo json_encode($result) . "\n";
        } else {
            $count = 0;
            foreach ($result as $entity) {
                echo json_encode($entity->toArrayWith($rules)) . "\n";
                $count++;
            }
        }

        Console::summary(Convert::plural($count, 'entity', 'entities', true) . ' retrieved');
    }
}
