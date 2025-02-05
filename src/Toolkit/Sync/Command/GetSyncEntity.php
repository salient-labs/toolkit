<?php declare(strict_types=1);

namespace Salient\Sync\Command;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Sync\Exception\SyncEntityNotFoundExceptionInterface;
use Salient\Contract\Sync\DeferralPolicy;
use Salient\Contract\Sync\HydrationPolicy;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Facade\Console;
use Salient\Sync\SyncSerializeRules;
use Salient\Sync\SyncUtil;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Json;
use InvalidArgumentException;

/**
 * A generic sync entity retrieval command
 *
 * @api
 *
 * @template T of SyncEntityInterface
 */
final class GetSyncEntity extends AbstractSyncCommand
{
    private string $EntityBasename = '';
    /** @var class-string<SyncEntityInterface> */
    private string $Entity = SyncEntityInterface::class;
    private ?string $EntityId = null;
    private ?string $ProviderBasename = null;
    /** @var class-string<SyncProviderInterface> */
    private string $Provider = SyncProviderInterface::class;
    /** @var string[] */
    private array $Filter = [];
    private bool $Shallow = false;
    private bool $IncludeCanonicalId = false;
    private bool $IncludeMeta = false;
    private bool $Stream = false;
    /** @var string[] */
    private array $Field = [];
    private bool $Csv = false;

    // --

    /** @var array<string,string> */
    private array $Fields;

    public function getDescription(): string
    {
        return 'Get entities from ' . (
            $this->Entities
                ? 'a registered provider'
                : 'a provider'
        );
    }

    protected function getOptionList(): iterable
    {
        $entityBuilder = CliOption::build()
            ->name('entity')
            ->required();

        $entityIdBuilder = CliOption::build()
            ->name('entity_id')
            ->description(<<<EOF
The unique identifier of an entity to request

If not given, a list of entities is requested.
EOF)
            ->optionType(CliOptionType::VALUE_POSITIONAL)
            ->bindTo($this->EntityId);

        $providerBuilder = CliOption::build()
            ->long('provider')
            ->short('p')
            ->valueName('provider');

        if ($this->Entities) {
            yield from [
                $entityBuilder
                    ->optionType(CliOptionType::ONE_OF_POSITIONAL)
                    ->allowedValues(array_keys($this->Entities))
                    ->bindTo($this->EntityBasename),
                $entityIdBuilder,
                $providerBuilder
                    ->description(<<<EOF
The provider to request entities from

If not given, the entity's default provider is used.
EOF)
                    ->optionType(CliOptionType::ONE_OF)
                    ->allowedValues(array_keys($this->EntityProviders))
                    ->bindTo($this->ProviderBasename),
            ];
        } else {
            yield from [
                $entityBuilder
                    ->description('The fully-qualified name of the entity to request')
                    ->optionType(CliOptionType::VALUE_POSITIONAL)
                    ->bindTo($this->Entity),
                $entityIdBuilder,
                $providerBuilder
                    ->description('The fully-qualified name of the provider to request entities from')
                    ->optionType(CliOptionType::VALUE)
                    ->required()
                    ->bindTo($this->Provider),
            ];
        }

        yield from [
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
                ->long('field')
                ->short('F')
                ->valueName('(<field>|<field>=<title>)')
                ->description('Limit output to the given fields, e.g. "id,user.id=user id,title"')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->Field),
            CliOption::build()
                ->long('csv')
                ->short('c')
                ->description('Generate CSV output (implies `--shallow` if `--field` is not given)')
                ->bindTo($this->Csv),
        ];

        yield from $this->getGlobalOptionList();
    }

    protected function run(string ...$args)
    {
        $this->startRun();

        $this->Fields = [];

        if ($this->Entities) {
            /** @var class-string<T> */
            $entity = $this->Entities[$this->EntityBasename];

            $provider = $this->ProviderBasename ?? array_search(
                $this->App->getName(SyncUtil::getEntityTypeProvider($entity, SyncUtil::getStore($this->App))),
                $this->Providers,
            );

            if ($provider === false) {
                throw new CliInvalidArgumentsException(
                    sprintf('no default provider: %s', $entity)
                );
            }

            $provider = $this->Providers[$provider];
        } else {
            /** @var class-string<T> */
            $entity = $this->Entity;
            $provider = $this->Provider;

            if (!is_a(
                $this->App->getName($entity),
                SyncEntityInterface::class,
                true
            )) {
                throw new CliInvalidArgumentsException(sprintf(
                    '%s does not implement %s',
                    $entity,
                    SyncEntityInterface::class,
                ));
            }

            if (!is_a(
                $this->App->getName($provider),
                SyncProviderInterface::class,
                true
            )) {
                throw new CliInvalidArgumentsException(sprintf(
                    '%s does not implement %s',
                    $provider,
                    SyncProviderInterface::class,
                ));
            }

            if (!$this->App->has($provider)) {
                $this->App->singleton($provider);
            }
        }

        $provider = $this->App->get($provider);

        $entityProvider = SyncUtil::getEntityTypeProvider($entity, SyncUtil::getStore($this->App));
        if (!$provider instanceof $entityProvider) {
            throw new CliInvalidArgumentsException(sprintf(
                '%s does not service %s',
                get_class($provider),
                $entity,
            ));
        }

        try {
            $filter = Get::filter($this->Filter);
        } catch (InvalidArgumentException $ex) {
            throw new CliInvalidArgumentsException(sprintf(
                'invalid filter (%s)',
                $ex->getMessage(),
            ));
        }

        $context = $provider->getContext();
        if ($this->Shallow || ($this->Csv && !$this->Field)) {
            $context = $context
                ->withDeferralPolicy(DeferralPolicy::DO_NOT_RESOLVE)
                ->withHydrationPolicy(HydrationPolicy::SUPPRESS);
        }

        /** @var SyncSerializeRules<T> */
        $rules = $entity::getSerializeRules()
            ->withCanonicalId($this->IncludeCanonicalId)
            ->withDynamicProperties($this->IncludeMeta);

        if ($this->Field) {
            foreach ($this->Field as $field) {
                $field = explode('=', $field, 2);
                $this->Fields[$field[0]] = $field[1] ?? $field[0];
            }
        }

        Console::info(
            sprintf('Retrieving from %s:', $provider->getName()),
            $this->Store->getEntityTypeUri($entity)
                . ($this->EntityId === null ? '' : '/' . $this->EntityId)
        );

        if ($this->EntityId === null) {
            $result = $this->Stream
                ? $provider->with($entity, $context)->getList($filter)
                : $provider->with($entity, $context)->getListA($filter);
        } else {
            try {
                $result = $provider->with($entity, $context)->get($this->EntityId, $filter);
            } catch (SyncEntityNotFoundExceptionInterface $ex) {
                throw new CliInvalidArgumentsException(
                    sprintf(
                        'entity not found: %s (%s)',
                        $this->EntityId,
                        $ex->getMessage(),
                    )
                );
            }
        }

        $stdout = Console::getStdoutTarget();
        $tty = $stdout->isTty();

        if ($this->Csv) {
            if ($this->EntityId !== null) {
                $result = [$result];
            }

            File::writeCsv(
                'php://output',
                $result,
                true,
                null,
                fn(SyncEntityInterface $entity, int $index) =>
                    $this->serialize($entity, $rules, !$index),
                $count,
                $tty ? $stdout->getEol() : "\r\n",
                !$tty,
                !$tty,
            );
        } elseif ($this->Stream && $this->EntityId === null) {
            $eol = $tty ? $stdout->getEol() : \PHP_EOL;
            $count = 0;
            foreach ($result as $entity) {
                echo Json::prettyPrint($this->serialize($entity, $rules, !$count), 0, $eol) . $eol;
                $count++;
            }
        } else {
            if ($this->EntityId !== null) {
                $result = [$result];
            }

            $output = [];
            $count = 0;
            foreach ($result as $entity) {
                $output[] = $this->serialize($entity, $rules, !$count);
                $count++;
            }

            if ($this->EntityId !== null) {
                $output = array_shift($output);
            }

            $eol = $tty ? $stdout->getEol() : \PHP_EOL;
            echo Json::prettyPrint($output, 0, $eol) . $eol;
        }

        Console::summary(Inflect::format(
            $count,
            '{{#}} {{#:entity}} retrieved',
        ));
    }

    /**
     * @param T $entity
     * @param SyncSerializeRules<T> $rules
     * @return mixed[]
     */
    private function serialize(
        SyncEntityInterface $entity,
        SyncSerializeRules $rules,
        bool $check = false
    ): array {
        $entity = $entity->toArrayWith($rules);
        if (!$this->Fields) {
            return $entity;
        }
        foreach ($this->Fields as $field => $name) {
            if ($check && !Arr::has($entity, $field)) {
                throw new CliInvalidArgumentsException(sprintf(
                    'Invalid field: %s',
                    $field,
                ));
            }
            $result[$name] = Arr::get($entity, $field, null);
        }
        return $result;
    }
}
