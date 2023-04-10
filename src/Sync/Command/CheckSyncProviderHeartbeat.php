<?php declare(strict_types=1);

namespace Lkrms\Sync\Command;

use Lkrms\Cli\CliAppContainer;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionBuilder;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncStore;

/**
 * A generic sync provider heartbeat check command
 *
 */
final class CheckSyncProviderHeartbeat extends CliCommand
{
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
            $key = strtolower(Convert::classToBasename($provider, 'SyncProvider', 'Provider'));
            if (array_key_exists($key, $this->Providers)) {
                $this->Providers[$key] = null;
                continue;
            }
            $this->Providers[$key] = $provider;
        }
        $this->Providers = array_filter($this->Providers);

        natsort($this->Providers);
    }

    public function getShortDescription(): string
    {
        return 'Send a heartbeat request to '
            . (count($this->Providers) > 1
                ? 'one or more providers'
                : 'a provider');
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->long('provider')
                ->valueName('provider')
                ->description('The provider to check')
                ->multipleAllowed()
                ->if(
                    (bool) $this->Providers,
                    fn(CliOptionBuilder $build) =>
                        $build->optionType(CliOptionType::ONE_OF_POSITIONAL)
                              ->allowedValues(array_keys($this->Providers))
                              ->addAll()
                              ->defaultValue('ALL'),
                    fn(CliOptionBuilder $build) =>
                        $build->optionType(CliOptionType::VALUE_POSITIONAL)
                              ->required()
                ),
            CliOption::build()
                ->long('ttl')
                ->short('t')
                ->valueName('seconds')
                ->description('The time-to-live of a positive result')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('300'),
            CliOption::build()
                ->long('fail-early')
                ->short('f')
                ->description('If a check fails, exit without checking other providers'),
        ];
    }

    public function getLongDescription(): ?string
    {
        !$this->Providers || $description = <<<EOF
If no providers are given, all providers are checked.


EOF;

        return ($description ?? '') . <<<EOF
If a heartbeat request fails, __{{command}}__ continues to the next
provider unless --fail-early is given, in which case it exits
immediately.

The command exits with a non-zero status if a provider backend is
unreachable.
EOF;
    }

    public function getUsageSections(): ?array
    {
        return null;
    }

    protected function run(string ...$args)
    {
        Console::registerStderrTarget(true);

        $providers = $this->getOptionValue('provider');

        if ($this->Providers) {
            $providers = array_map(
                fn(string $providerClass) =>
                    $this->app()->get($providerClass),
                array_intersect_key(
                    $this->Providers,
                    array_flip($providers)
                )
            );
        } else {
            $providers = array_map(
                fn(string $providerClass) =>
                    $this->app()->getIf($providerClass, ISyncProvider::class),
                $providers
            );
        }

        $count = count($providers);

        Console::info(
            sprintf(
                'Sending heartbeat request to %d %s',
                $count,
                Convert::plural($count, 'provider')
            ),
        );

        $this->Store->checkHeartbeats(
            (int) $this->getOptionValue('ttl'),
            (bool) $this->getOptionValue('fail-early'),
            ...$providers
        );

        Console::summary(Convert::plural($count, 'provider', null, true) . ' checked');
    }
}
