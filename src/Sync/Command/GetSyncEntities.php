<?php declare(strict_types=1);

namespace Lkrms\Sync\Command;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\Contract\ICliApplication;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\File;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncStore;

/**
 * A generic sync entity retrieval command
 *
 */
final class GetSyncEntities extends CliCommand
{
    /**
     * Unambiguous lowercase entity basename => entity
     *
     * @var array<string,class-string<ISyncEntity>|null>
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
     * @var array<string,class-string<ISyncProvider>>
     */
    private $Providers = [];

    /**
     * @var SyncStore
     */
    private $Store;

    public function __construct(ICliApplication $container, SyncStore $store)
    {
        parent::__construct($container);

        $this->Store = $store;

        foreach ($this->App->getServices() as $provider) {
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
                ->valueName('term=value')
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

    public function getHelpSections(): ?array
    {
        return null;
    }

    protected function run(string ...$args)
    {
        Console::registerStderrTarget(true);

        $class = $this->Entities[$this->getOptionValue('type')];
        $provider = $this->getOptionValue('provider');
        $id = $this->getOptionValue('id');
        $filter = Convert::queryToData($this->getOptionValue('filter'));
        $stream = $this->getOptionValue('stream');
        $csv = $this->getOptionValue('csv');

        if (!($provider = $provider ?: array_search(
            $this->App->getName(SyncIntrospector::entityToProvider($class)),
            $this->Providers
        ))) {
            throw new CliInvalidArgumentsException('no default provider: ' . $class);
        }
        /** @var ISyncProvider */
        $provider = $this->App->get($this->Providers[$provider]);

        Console::info(
            'Retrieving from ' . $provider->name() . ':',
            ($this->Store->getEntityTypeUri($class)
                    ?: '/' . str_replace('\\', '/', ltrim($class, '\\')))
                . (is_null($id) ? '' : "/$id")
        );

        $this->App->bindIf(ISyncContext::class, SyncContext::class);
        $context = $this->App->get(ISyncContext::class);

        $result = !is_null($id)
            ? $provider->with($class, $context)->get($id, $filter)
            : ($stream
                ? $provider->with($class, $context)->getList($filter)
                : $provider->with($class, $context)->getListA($filter));

        $rules = $class::buildSerializeRules($this->App)->includeMeta(false);

        if ($csv) {
            if (!is_null($id)) {
                $result = Convert::toList($result, true);
            }

            File::writeCsv(
                $result,
                'php://stdout',
                true,
                null,
                $count,
                fn(ISyncEntity $entity) => $entity->toArrayWith($rules)
            );
        } elseif (!is_iterable($result) || !$stream) {
            $result = Convert::toList($result, true);
            /** @var ISyncEntity $entity */
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
