<?php declare(strict_types=1);

namespace Lkrms\Sync\Command;

use Lkrms\Cli\CliAppContainer;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\File;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncStore;

final class GetSyncEntities extends CliCommand
{
    /**
     * Unambiguous lowercase entity basename => entity
     *
     * @var array<string,string>
     */
    private $Entities = [];

    /**
     * Provider => entity
     *
     * @var array<string,string>
     */
    private $ProviderEntities = [];

    /**
     * Unambiguous lowercase provider basename => provider
     *
     * @var array<string,string>
     */
    private $Providers = [];

    /**
     * @var SyncStore
     */
    private $Store;

    public function __construct(CliAppContainer $container, SyncStore $store)
    {
        parent::__construct($container);

        $this->Store = $store;

        foreach ($this->app()->getServices() as $provider) {
            if (!is_a($provider, ISyncProvider::class, true)) {
                continue;
            }

            $introspector = SyncIntrospector::get($provider);
            foreach ($introspector->getSyncProviderEntityBasenames() as $basename => $entity) {
                $this->ProviderEntities[$provider] = $entity;
                if (array_key_exists($basename, $this->Entities) &&
                    (is_null($this->Entities[$basename]) ||
                        strcasecmp($this->Entities[$basename], $entity))) {
                    $this->Entities[$basename] = null;
                    continue;
                }
                $this->Entities[$basename] = $entity;
            }
            $this->Entities = array_filter($this->Entities);
        }

        foreach (array_keys($this->ProviderEntities) as $provider) {
            $this->Providers[strtolower(Convert::classToBasename($provider, 'SyncProvider', 'Provider'))] = $provider;
        }

        natsort($this->Entities);
        natsort($this->Providers);
    }

    public function getShortDescription(): string
    {
        return 'Get data from a provider';
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->long('type')
                ->short('t')
                ->description('The entity type to retrieve')
                ->optionType(CliOptionType::ONE_OF)
                ->valueName('entity_type')
                ->allowedValues(array_keys($this->Entities))
                ->required(),
            CliOption::build()
                ->long('provider')
                ->short('i')
                ->description('The provider to request data from')
                ->optionType(CliOptionType::ONE_OF)
                ->valueName('provider')
                ->allowedValues(array_keys($this->Providers)),
            CliOption::build()
                ->long('id')
                ->short('n')
                ->description('The unique identifier of a particular entity')
                ->optionType(CliOptionType::VALUE)
                ->valueName('entity_id'),
            CliOption::build()
                ->long('filter')
                ->short('f')
                ->valueName('TERM=VALUE')
                ->description('Pass a filter to the provider')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed(),
            CliOption::build()
                ->long('stream')
                ->short('s')
                ->description('Output a stream of entities'),
            CliOption::build()
                ->long('csv')
                ->short('c')
                ->description('Generate CSV output'),
        ];
    }

    public function getLongDescription(): ?string
    {
        return null;
    }

    public function getUsageSections(): ?array
    {
        return null;
    }

    protected function run(string ...$params)
    {
        Console::registerStderrTarget(true);

        $class    = $this->Entities[$this->getOptionValue('type')];
        $provider = $this->getOptionValue('provider');
        $id       = $this->getOptionValue('id');
        $filter   = Convert::queryToData($this->getOptionValue('filter'));
        $stream   = $this->getOptionValue('stream');
        $csv      = $this->getOptionValue('csv');

        if (!($provider = $provider ?: array_search(
            $this->app()->getName(SyncIntrospector::entityToProvider($class)),
            $this->Providers
        ))) {
            throw new CliArgumentsInvalidException('no default provider: ' . $class);
        }
        /** @var ISyncProvider */
        $provider = $this->app()->get($this->Providers[$provider]);

        Console::info('Retrieving from ' . $provider->name() . ':',
                      $this->Store->getEntityTypeUri($class) . (is_null($id) ? '' : "/$id"));

        $context = new SyncContext($this->app());
        if (!$stream) {
            $context = $context->withListArrays();
        }

        $result = !is_null($id)
            ? $provider->with($class, $context)->get($id, $filter)
            : $provider->with($class, $context)->getList($filter);

        $rules = $class::buildSerializeRules($this->app())->includeMeta(false);

        if ($csv) {
            if (!is_null($id)) {
                $result = Convert::toList($result, true);
            }

            File::writeCsv($result, 'php://stdout', true, null, $count,
                           fn(SyncEntity $entity) => $entity->toArrayWith($rules));
        } elseif (!is_iterable($result) || !$stream) {
            $result = Convert::toList($result, true);
            /** @var SyncEntity $entity */
            foreach ($result as &$entity) {
                $entity = $entity->toArrayWith($rules);
            }
            $count = count($result);
            if (!is_null($id)) {
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
