<?php declare(strict_types=1);

namespace Salient\Sync\Command;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliCommand;
use Salient\Cli\CliOption;
use Salient\Cli\CliOptionBuilder;
use Salient\Contract\Cli\CliApplicationInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\Facade\Console;
use Salient\Sync\Http\HttpSyncProvider;
use Salient\Sync\Support\SyncIntrospector;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Json;
use Salient\Utility\Str;
use JsonException;

/**
 * @internal
 */
abstract class AbstractSyncCommand extends CliCommand
{
    protected bool $ExportHar = false;

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
                    if (isset($httpProviders[$providerKey])) {
                        $httpProviders[$httpProviders[$providerKey]] = $httpProviders[$providerKey];
                        $httpProviders[$providerKey] = null;
                    }
                }
                $providers[$provider] = $provider;
                if ($entityBasenames) {
                    $entityProviders[$provider] = $provider;
                }
                if (is_a($provider, HttpSyncProvider::class, true)) {
                    $httpProviders[$provider] = $provider;
                }
                continue;
            }

            $providers[$providerKey] = $provider;
            if ($entityBasenames) {
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
     * @return array<CliOption|CliOptionBuilder>
     */
    protected function getGlobalOptionList(): array
    {
        return [
            CliOption::build()
                ->long('har')
                ->short('H')
                ->description('Export HTTP requests to an HTTP Archive file in the log directory')
                ->bindTo($this->ExportHar),
        ];
    }

    protected function startRun(): void
    {
        Console::registerStderrTarget();

        if ($this->ExportHar) {
            $this->App->exportHar();
        }
    }

    /**
     * @return mixed[]|object
     */
    protected function getJson(string $filename, bool $associative = true)
    {
        $json = File::getContents($filename === '-' ? 'php://stdin' : $filename);

        try {
            $json = $associative
                ? Json::parseObjectAsArray($json)
                : Json::parse($json);
        } catch (JsonException $ex) {
            $message = $ex->getMessage();
            throw new CliInvalidArgumentsException(
                $filename === '-'
                    ? sprintf('invalid JSON: %s', $message)
                    : sprintf("invalid JSON in '%s': %s", $filename, $message)
            );
        }

        if (!is_array($json) && ($associative || !is_object($json))) {
            throw new CliInvalidArgumentsException(
                $filename === '-'
                    ? 'invalid payload'
                    : sprintf('invalid payload: %s', $filename)
            );
        }

        return $json;
    }
}
