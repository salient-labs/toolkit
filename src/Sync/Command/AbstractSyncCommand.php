<?php declare(strict_types=1);

namespace Lkrms\Sync\Command;

use Lkrms\Cli\Contract\ICliApplication;
use Lkrms\Cli\CliCommand;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Get;
use Lkrms\Utility\Str;

/**
 * Base class for generic sync commands
 */
abstract class AbstractSyncCommand extends CliCommand
{
    /**
     * @var SyncStore
     */
    protected $Store;

    /**
     * Entities serviced by sync providers
     *
     * Unambiguous kebab-case entity basename => entity
     *
     * @var array<string,class-string<ISyncEntity>>
     */
    protected $Entities = [];

    /**
     * Sync providers
     *
     * Unambiguous kebab-case provider basename => provider
     *
     * @var array<string,class-string<ISyncProvider>>
     */
    protected $Providers = [];

    /**
     * Sync providers that service at least one entity
     *
     * Unambiguous kebab-case provider basename => provider
     *
     * @var array<string,class-string<ISyncProvider>>
     */
    protected $EntityProviders = [];

    public function __construct(ICliApplication $container, SyncStore $store)
    {
        parent::__construct($container);

        $this->Store = $store;

        $entities = [];
        $providers = [];
        $entityProviders = [];
        foreach ($this->App->getServices() as $provider) {
            if (!is_a($provider, ISyncProvider::class, true)) {
                continue;
            }

            $providerKey = Str::toKebabCase(
                Get::basename($provider, 'SyncProvider', 'Provider')
            );

            $introspector = SyncIntrospector::get($provider);
            $entityBasenames = $introspector->getSyncProviderEntityBasenames();
            foreach ($entityBasenames as $entityKey => $entity) {
                $entityProviders[$providerKey] = $provider;
                if (array_key_exists($entityKey, $entities) &&
                    ($entities[$entityKey] === null ||
                        strcasecmp($entities[$entityKey], $entity))) {
                    $entities[$entityKey] = null;
                    continue;
                }
                $entities[$entityKey] = $entity;
            }

            if (array_key_exists($providerKey, $providers)) {
                $providers[$providerKey] = null;
                $entityProviders[$providerKey] = null;
                continue;
            }
            $providers[$providerKey] = $provider;
        }

        $this->Entities = Arr::sort(Arr::whereNotNull($entities), true);
        $this->Providers = Arr::sort(Arr::whereNotNull($providers), true);
        $this->EntityProviders = Arr::sort(Arr::whereNotNull($entityProviders), true);
    }

    public function getLongDescription(): ?string
    {
        return null;
    }

    public function getHelpSections(): ?array
    {
        return null;
    }
}
