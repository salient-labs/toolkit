<?php declare(strict_types=1);

namespace Salient\Sync\Command;

use Salient\Cli\CliCommand;
use Salient\Cli\CliOption;
use Salient\Cli\CliOptionBuilder;
use Salient\Contract\Cli\CliApplicationInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\Facade\Console;
use Salient\Sync\Http\HttpSyncProvider;
use Salient\Sync\Reflection\SyncProviderReflection;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Str;

/**
 * @internal
 */
abstract class AbstractSyncCommand extends CliCommand
{
    protected bool $RecordHar = false;

    // --

    protected SyncStoreInterface $Store;

    /**
     * Entities serviced by sync providers
     *
     * Unambiguous kebab-case basename => entity
     *
     * @var array<string,class-string<SyncEntityInterface>>
     */
    protected array $Entities = [];

    /**
     * Sync providers
     *
     * Unambiguous kebab-case basename or FQCN => provider
     *
     * @var array<string,class-string<SyncProviderInterface>>
     */
    protected array $Providers = [];

    /**
     * Sync providers that service at least one entity
     *
     * Unambiguous kebab-case basename or FQCN => provider
     *
     * @var array<string,class-string<SyncProviderInterface>>
     */
    protected array $EntityProviders = [];

    /**
     * HTTP sync providers
     *
     * Unambiguous kebab-case basename or FQCN => provider
     *
     * @var array<string,class-string<HttpSyncProvider>>
     */
    protected array $HttpProviders = [];

    public function __construct(CliApplicationInterface $container, SyncStoreInterface $store)
    {
        parent::__construct($container);

        $this->Store = $store;

        $entities = [];
        $providers = [];
        $entityProviders = [];
        $httpProviders = [];
        foreach ($this->App->getProviders() as $provider) {
            if (!is_a($provider, SyncProviderInterface::class, true)) {
                continue;
            }

            $basenames = (new SyncProviderReflection($provider))
                ->getSyncProviderEntityTypeBasenames();
            foreach ($basenames as $entityKey => $entity) {
                if (array_key_exists($entityKey, $entities) && (
                    $entities[$entityKey] === null
                    || $entities[$entityKey] !== $entity
                )) {
                    $entities[$entityKey] = null;
                    continue;
                }

                $entities[$entityKey] = $entity;
            }

            $providerKey = Str::kebab(
                Get::basename($provider, 'SyncProvider', 'Provider')
            );

            if (array_key_exists($providerKey, $providers)) {
                if ($providers[$providerKey] !== null) {
                    $providers[$providers[$providerKey]] = $providers[$providerKey];
                    $providers[$providerKey] = null;
                    if (isset($entityProviders[$providerKey])) {
                        $entityProviders[$entityProviders[$providerKey]] = $entityProviders[$providerKey];
                        $entityProviders[$providerKey] = null;
                    }
                    if (isset($httpProviders[$providerKey])) {
                        $httpProviders[$httpProviders[$providerKey]] = $httpProviders[$providerKey];
                        $httpProviders[$providerKey] = null;
                    }
                }
                $providers[$provider] = $provider;
                if ($basenames) {
                    $entityProviders[$provider] = $provider;
                }
                if (is_a($provider, HttpSyncProvider::class, true)) {
                    $httpProviders[$provider] = $provider;
                }
                continue;
            }

            $providers[$providerKey] = $provider;
            if ($basenames) {
                $entityProviders[$providerKey] = $provider;
            }
            if (is_a($provider, HttpSyncProvider::class, true)) {
                $httpProviders[$providerKey] = $provider;
            }
        }

        $this->Entities = Arr::sortByKey(Arr::whereNotNull($entities));
        $this->Providers = Arr::sortByKey(Arr::whereNotNull($providers));
        $this->EntityProviders = Arr::sortByKey(Arr::whereNotNull($entityProviders));
        $this->HttpProviders = Arr::sortByKey(Arr::whereNotNull($httpProviders));
    }

    /**
     * @return iterable<CliOption|CliOptionBuilder>
     */
    protected function getGlobalOptionList(): iterable
    {
        yield from [
            CliOption::build()
                ->long('har')
                ->short('H')
                ->description('Record HTTP requests to an HTTP Archive file in the log directory')
                ->bindTo($this->RecordHar),
        ];
    }

    protected function startRun(): void
    {
        Console::registerStderrTarget();

        if ($this->RecordHar) {
            $this->App->recordHar();
        }
    }
}
