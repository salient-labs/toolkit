<?php declare(strict_types=1);

namespace Salient\Sync\Command;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Sync\DeferralPolicy;
use Salient\Contract\Sync\HydrationPolicy;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Facade\Console;
use Salient\Sync\Support\SyncIntrospector;
use Salient\Sync\SyncSerializeRules;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Json;

/**
 * A generic sync entity retrieval command
 *
 * @api
 *
 * @template T of SyncEntityInterface
 */
final class GetSyncEntities extends AbstractSyncCommand
{
    private string $Entity = '';
    private ?string $EntityId = null;
    private ?string $Provider = null;
    /** @var string[] */
    private array $Filter = [];
    private bool $Shallow = false;
    private bool $IncludeCanonicalId = false;
    private bool $IncludeMeta = false;
    private bool $Stream = false;
    private bool $Csv = false;

    public function getDescription(): string
    {
        return 'Get entities from a provider';
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->name('entity')
                ->description('The entity to request')
                ->optionType(CliOptionType::ONE_OF_POSITIONAL)
                ->allowedValues(array_keys($this->Entities))
                ->required()
                ->bindTo($this->Entity),
            CliOption::build()
                ->name('entity_id')
                ->description(<<<EOF
The unique identifier of an entity to request

If not given, a list of entities is requested.
EOF)
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->bindTo($this->EntityId),
            CliOption::build()
                ->long('provider')
                ->short('p')
                ->description(<<<EOF
The provider to request entities from

If not given, the entity's default provider is used.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->valueName('provider')
                ->allowedValues(array_keys($this->EntityProviders))
                ->bindTo($this->Provider),
            CliOption::build()
                ->long('filter')
                ->short('f')
                ->valueName('term=value')
                ->description('Apply a filter to the request')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->Filter),
            CliOption::build()
                ->long('shallow')
                ->description('Do not resolve entity relationships')
                ->bindTo($this->Shallow),
            CliOption::build()
                ->long('include-canonical-id')
                ->short('I')
                ->description('Include canonical_id in the output')
                ->bindTo($this->IncludeCanonicalId),
            CliOption::build()
                ->long('include-meta')
                ->short('M')
                ->description('Include meta values in the output')
                ->bindTo($this->IncludeMeta),
            CliOption::build()
                ->long('stream')
                ->short('s')
                ->description('Output a stream of entities')
                ->bindTo($this->Stream),
            CliOption::build()
                ->long('csv')
                ->short('c')
                ->description('Generate CSV output (implies `--shallow`)')
                ->bindTo($this->Csv),
        ];
    }

    protected function run(string ...$args)
    {
        Console::registerStderrTarget();

        /** @var class-string<T> */
        $entity = $this->Entities[$this->Entity];

        $provider = $this->Provider ?? array_search(
            $this->App->getName(SyncIntrospector::entityToProvider($entity)),
            $this->Providers,
        );

        if ($provider === false) {
            throw new CliInvalidArgumentsException(
                sprintf('no default provider: %s', $entity)
            );
        }

        /** @var SyncProviderInterface */
        $provider = $this->App->get($this->Providers[$provider]);

        $filter = Get::filter($this->Filter);

        $context = $provider->getContext();
        if ($this->Shallow || $this->Csv) {
            $context = $context
                ->withDeferralPolicy(DeferralPolicy::DO_NOT_RESOLVE)
                ->withHydrationPolicy(HydrationPolicy::SUPPRESS);
        }

        /** @var SyncSerializeRules<T> */
        $rules = $entity::getSerializeRules()
            ->withIncludeCanonicalId($this->IncludeCanonicalId)
            ->withIncludeMeta($this->IncludeMeta);

        Console::info(
            sprintf('Retrieving from %s:', $provider->getName()),
            ($this->Store->getEntityUri($entity)
                    ?? '/' . str_replace('\\', '/', $entity))
                . ($this->EntityId === null ? '' : '/' . $this->EntityId)
        );

        if ($this->EntityId === null) {
            $result = $this->Stream
                ? $provider->with($entity, $context)->getList($filter)
                : $provider->with($entity, $context)->getListA($filter);
        } else {
            $result = $provider->with($entity, $context)->get($this->EntityId, $filter);
        }

        if ($this->Csv) {
            if ($this->EntityId !== null) {
                $result = [$result];
            }

            $tty = Console::getStdoutTarget()->isTty();

            File::writeCsv(
                'php://output',
                $result,
                true,
                null,
                fn(SyncEntityInterface $entity) => $entity->toArrayWith($rules),
                $count,
                $tty ? \PHP_EOL : "\r\n",
                !$tty,
                !$tty,
            );
        } elseif ($this->Stream && $this->EntityId === null) {
            $count = 0;
            foreach ($result as $entity) {
                echo Json::prettyPrint($entity->toArrayWith($rules)) . \PHP_EOL;
                $count++;
            }
        } else {
            if ($this->EntityId !== null) {
                $result = [$result];
            }

            $output = [];
            $count = 0;
            foreach ($result as $entity) {
                $output[] = $entity->toArrayWith($rules);
                $count++;
            }

            if ($this->EntityId !== null) {
                if ($output) {
                    $output = array_shift($output);
                } else {
                    throw new CliInvalidArgumentsException(
                        sprintf('entity not found: %s', $this->EntityId)
                    );
                }
            }

            echo Json::prettyPrint($output) . \PHP_EOL;
        }

        Console::summary(Inflect::format(
            $count,
            '{{#}} {{#:entity}} retrieved',
        ));
    }
}
