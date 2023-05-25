<?php declare(strict_types=1);

namespace Lkrms\Sync\Command;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\Contract\ICliApplication;
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
     * @var array<string,class-string<ISyncProvider>>
     */
    private $Providers = [];

    /**
     * @var string[]
     */
    private $ProviderBasename;

    /**
     * @var array<class-string<ISyncProvider>>
     */
    private $Provider;

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

    public function description(): string
    {
        return 'Send a heartbeat request to '
            . (count($this->Providers) > 1
                ? 'one or more providers'
                : 'a provider');
    }

    protected function getOptionList(): array
    {
        $optB = CliOption::build()
            ->long('provider')
            ->valueName('provider')
            ->description('The provider to check')
            ->multipleAllowed();

        if ($this->Providers) {
            $optB = $optB->optionType(CliOptionType::ONE_OF_POSITIONAL)
                         ->allowedValues(array_keys($this->Providers))
                         ->addAll()
                         ->defaultValue('ALL')
                         ->bindTo($this->ProviderBasename);
        } else {
            $optB = $optB->optionType(CliOptionType::VALUE_POSITIONAL)
                         ->required()
                         ->bindTo($this->Provider);
        }

        return [
            $optB,
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

    public function getHelpSections(): ?array
    {
        return null;
    }

    protected function run(string ...$args)
    {
        Console::registerStderrTarget(true);

        if ($this->Providers) {
            $providers = array_map(
                fn(string $providerClass) =>
                    $this->App->get($providerClass),
                array_intersect_key(
                    $this->Providers,
                    array_flip($this->ProviderBasename)
                )
            );
        } else {
            $providers = array_map(
                fn(string $providerClass) =>
                    $this->App->getIf($providerClass, ISyncProvider::class),
                $this->Provider
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
