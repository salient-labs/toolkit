<?php declare(strict_types=1);

namespace Lkrms\Sync\Command;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\CliOption;
use Lkrms\Facade\Console;
use Lkrms\Facade\File;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncSerializeRules;
use Lkrms\Utility\Convert;

/**
 * A generic sync entity retrieval command
 *
 * @template T of ISyncEntity
 */
final class GetSyncEntities extends AbstractSyncCommand
{
    private ?string $Entity;

    private ?string $EntityId;

    private ?string $Provider;

    /**
     * @var string[]|null
     */
    private ?array $Filter;

    private ?bool $IncludeCanonical;

    private ?bool $IncludeMeta;

    private ?bool $Stream;

    private ?bool $Csv;

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
                ->long('include-canonical-id')
                ->short('I')
                ->description('Include canonical_id in the output')
                ->bindTo($this->IncludeCanonical),
            CliOption::build()
                ->long('include-meta')
                ->short('M')
                ->description('Include meta values in the output')
                ->bindTo($this->IncludeMeta),
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
        $provider =
            $this->Provider === null
                ? array_search(
                    $this->App->getName(SyncIntrospector::entityToProvider($entity)),
                    $this->Providers,
                )
                : $this->Provider;

        if ($provider === false) {
            throw new CliInvalidArgumentsException('no default provider: ' . $entity);
        }

        $filter = Convert::queryToData($this->Filter);

        /** @var ISyncProvider */
        $provider = $this->App->get($this->Providers[$provider]);

        $entityUri = $this->Store->getEntityTypeUri($entity);
        if ($entityUri === null) {
            $entityUri = '/' . str_replace('\\', '/', ltrim($entity, '\\'));
        }

        $entityId =
            $this->EntityId === null
                ? ''
                : '/' . $this->EntityId;

        Console::info(
            'Retrieving from ' . $provider->name() . ':',
            $entityUri . $entityId
        );

        $context = $provider->getContext();

        $result = $this->EntityId !== null
            ? $provider->with($entity, $context)->get($this->EntityId, $filter)
            : ($this->Stream
                ? $provider->with($entity, $context)->getList($filter)
                : $provider->with($entity, $context)->getListA($filter));

        /** @var SyncSerializeRules<T> */
        $rules = $entity::getSerializeRules($this->App);
        if (!$this->IncludeMeta) {
            $rules = $rules->withIncludeMeta(false);
        }
        if ($this->IncludeCanonical) {
            $rules = $rules->withRemoveCanonicalId(false);
        }

        if ($this->Csv) {
            if ($this->EntityId !== null) {
                $result = [$result];
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
                !$tty,
                !$tty,
            );
        } elseif (!is_iterable($result) || !$this->Stream) {
            $result = (array) $result;
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

        Console::summary(
            Convert::plural(
                $count, 'entity', 'entities', true
            ) . ' retrieved'
        );
    }
}
