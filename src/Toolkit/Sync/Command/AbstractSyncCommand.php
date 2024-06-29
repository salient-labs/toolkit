<?php declare(strict_types=1);

namespace Salient\Sync\Command;

use Salient\Cli\CliCommand;
use Salient\Contract\Cli\CliApplicationInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Sync\Support\SyncIntrospector;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Str;

/**
 * Base class for generic sync commands
 */
abstract class AbstractSyncCommand extends CliCommand
{
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

    public function __construct(CliApplicationInterface $container, SyncStoreInterface $store)
    {
        parent::__construct($container);

        $this->Store = $store;

        $entities = [];
        $providers = [];
        $entityProviders = [];
        foreach ($this->App->getProviders() as $provider) {
            if (!is_a($provider, SyncProviderInterface::class, true)) {
                continue;
            }

            $introspector = SyncIntrospector::get($provider);
            $entityBasenames = $introspector->getSyncProviderEntityBasenames();
            foreach ($entityBasenames as $entityKey => $entity) {
                if (array_key_exists($entityKey, $entities) && (
                    $entities[$entityKey] === null
                    || $entities[$entityKey] !== $entity
                )) {
                    $entities[$entityKey] = null;
                    continue;
                }

                $entities[$entityKey] = $entity;
            }

            $providerKey = Str::toKebabCase(
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
                }
                $providers[$provider] = $provider;
                if ($entityBasenames) {
                    $entityProviders[$provider] = $provider;
                }
                continue;
            }

            $providers[$providerKey] = $provider;
            if ($entityBasenames) {
                $entityProviders[$providerKey] = $provider;
            }
        }

        $this->Entities = Arr::sortByKey(Arr::whereNotNull($entities));
        $this->Providers = Arr::sortByKey(Arr::whereNotNull($providers));
        $this->EntityProviders = Arr::sortByKey(Arr::whereNotNull($entityProviders));
    }
}
