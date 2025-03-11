<?php declare(strict_types=1);

namespace Salient\Sync\Command;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Facade\Console;
use Salient\Utility\Inflect;

/**
 * A generic sync provider heartbeat check command
 *
 * @api
 */
final class CheckSyncProviderHeartbeat extends AbstractSyncCommand
{
    /** @var string[] */
    private array $ProviderBasename = [];
    /** @var array<class-string<SyncProviderInterface>> */
    private array $Provider = [];
    private int $Ttl = 0;
    private bool $FailEarly = false;

    public function getDescription(): string
    {
        return 'Send a heartbeat request to ' . (
            $this->Providers
                ? 'registered providers'
                : 'one or more providers'
        );
    }

    protected function getOptionList(): iterable
    {
        $builder = CliOption::build()
            ->name('provider')
            ->multipleAllowed();

        if ($this->Providers) {
            yield $builder
                ->optionType(CliOptionType::ONE_OF_POSITIONAL)
                ->allowedValues(array_keys($this->Providers))
                ->addAll()
                ->defaultValue('ALL')
                ->bindTo($this->ProviderBasename);
        } else {
            yield $builder
                ->description('The fully-qualified name of the provider to check')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required()
                ->bindTo($this->Provider);
        }

        yield from [
            CliOption::build()
                ->long('ttl')
                ->short('t')
                ->valueName('seconds')
                ->description('The lifetime of a positive result, in seconds')
                ->optionType(CliOptionType::VALUE)
                ->valueType(CliOptionValueType::INTEGER)
                ->defaultValue(300)
                ->bindTo($this->Ttl),
            CliOption::build()
                ->long('fail-early')
                ->short('f')
                ->description('If a check fails, exit without checking other providers')
                ->bindTo($this->FailEarly),
        ];

        yield from $this->getGlobalOptionList();
    }

    // @phpstan-ignore return.unusedType
    protected function getLongDescription(): ?string
    {
        if ($this->Providers) {
            $description[] = <<<EOF
If no providers are given, all providers are checked.
EOF;
        }

        $description[] = <<<EOF
If a heartbeat request fails, __{{subcommand}}__ continues to the next provider
unless `-f/--fail-early` is given, in which case it exits immediately.

The command exits with a non-zero status if a provider backend is unreachable.
EOF;

        return implode(\PHP_EOL . \PHP_EOL, $description);
    }

    protected function run(string ...$args)
    {
        $this->startRun();

        if ($this->Providers) {
            $providers = array_values(array_map(
                fn(string $providerClass) =>
                    $this->App->get($providerClass),
                array_intersect_key(
                    $this->Providers,
                    array_flip($this->ProviderBasename),
                ),
            ));
        } else {
            $providers = array_values(array_map(
                function (string $providerClass) {
                    if (is_a(
                        $this->App->getClass($providerClass),
                        SyncProviderInterface::class,
                        true
                    )) {
                        if (!$this->App->has($providerClass)) {
                            $this->App->singleton($providerClass);
                        }
                        return $this->App->get($providerClass);
                    }

                    throw new CliInvalidArgumentsException(sprintf(
                        '%s does not implement %s',
                        $providerClass,
                        SyncProviderInterface::class,
                    ));
                },
                $this->Provider,
            ));
        }

        $count = count($providers);

        Console::info(Inflect::format(
            $count,
            'Sending heartbeat request to {{#}} {{#:provider}}',
        ));

        $this->Store->checkProviderHeartbeats(
            max(1, $this->Ttl),
            $this->FailEarly,
            ...$providers,
        );

        Console::summary(Inflect::format(
            $count,
            '{{#}} {{#:provider}} checked',
        ));
    }
}
