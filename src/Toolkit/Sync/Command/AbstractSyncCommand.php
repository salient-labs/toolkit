<?php declare(strict_types=1);

namespace Salient\Sync\Command;

use Salient\Cli\Contract\CliApplicationInterface;
use Salient\Cli\CliCommand;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Str;
use Salient\Sync\Contract\SyncEntityInterface;
use Salient\Sync\Contract\SyncProviderInterface;
use Salient\Sync\Support\SyncIntrospector;
use Salient\Sync\SyncStore;

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
     * @var array<string,class-string<SyncEntityInterface>>
     */
    protected $Entities = [];

    /**
     * Sync providers
     *
     * Unambiguous kebab-case provider basename => provider
     *
     * @var array<string,class-string<SyncProviderInterface>>
     */
    protected $Providers = [];

    /**
     * Sync providers that service at least one entity
     *
     * Unambiguous kebab-case provider basename => provider
     *
     * @var array<string,class-string<SyncProviderInterface>>
     */
    protected $EntityProviders = [];

    public function __construct(CliApplicationInterface $container, SyncStore $store)
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
}
