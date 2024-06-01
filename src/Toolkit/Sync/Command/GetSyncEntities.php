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
use Salient\Core\Utility\File;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Inflect;
use Salient\Core\Utility\Json;
use Salient\Sync\Support\SyncIntrospector;
use Salient\Sync\SyncSerializeRules;

/**
 * A generic sync entity retrieval command
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
    private bool $IncludeCanonical = false;
    private bool $IncludeMeta = false;
    private bool $Stream = false;
    private bool $Csv = false;

    public function description(): string
    {
        return 'Get data from a provider';
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->long('type')
                ->description('The entity type to request')
                ->optionType(CliOptionType::ONE_OF_POSITIONAL)
                ->valueName('entity_type')
                ->allowedValues(array_keys($this->Entities))
                ->required()
                ->bindTo($this->Entity),
            CliOption::build()
                ->long('id')
                ->description('The unique identifier of a particular entity')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueName('entity_id')
                ->bindTo($this->EntityId),
            CliOption::build()
                ->long('provider')
                ->short('p')
                ->description('The provider to request data from')
                ->optionType(CliOptionType::ONE_OF)
                ->valueName('provider')
                ->allowedValues(array_keys($this->EntityProviders))
                ->bindTo($this->Provider),
            CliOption::build()
                ->long('filter')
                ->short('f')
                ->valueName('term=value')
                ->description('Pass a filter to the provider')
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
                ->bindTo($this->IncludeCanonical),
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
        Console::registerStderrTarget(true);

        $entity = $this->Entities[$this->Entity];
        $provider =
            $this->Provider === null
                ? array_search(
                    $this->App->getName(SyncIntrospector::entityToProvider($entity)),
                    $this->Providers,
                )
                : $this->Provider;

        if ($provider === false) {
            throw new CliInvalidArgumentsException('no default provider: ' . $entity);
        }

        $filter = Get::filter($this->Filter);

        /** @var SyncProviderInterface */
        $provider = $this->App->get($this->Providers[$provider]);

        $entityUri = $this->Store->getEntityUri($entity);
        if ($entityUri === null) {
            $entityUri = '/' . str_replace('\\', '/', ltrim($entity, '\\'));
        }

        $entityId =
            $this->EntityId === null
                ? ''
                : '/' . $this->EntityId;

        Console::info(
            'Retrieving from ' . $provider->name() . ':',
            $entityUri . $entityId
        );

        $context = $provider->getContext();
        if ($this->Shallow || $this->Csv) {
            $context = $context
                ->withDeferralPolicy(DeferralPolicy::DO_NOT_RESOLVE)
                ->withHydrationPolicy(HydrationPolicy::SUPPRESS);
        }

        $result = $this->EntityId !== null
            ? $provider->with($entity, $context)->get($this->EntityId, $filter)
            : ($this->Stream
                ? $provider->with($entity, $context)->getList($filter)
                : $provider->with($entity, $context)->getListA($filter));

        /** @var SyncSerializeRules<T> */
        $rules = $entity::getSerializeRules($this->App);
        if (!$this->IncludeMeta) {
            $rules = $rules->withIncludeMeta(false);
        }
        if ($this->IncludeCanonical) {
            $rules = $rules->withIncludeCanonicalId();
        }

        if ($this->Csv) {
            if ($this->EntityId !== null) {
                $result = [$result];
            }

            $stdout = Console::getStdoutTarget();
            $tty = $stdout->isTty();
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
        } elseif (!is_iterable($result) || !$this->Stream) {
            $result = (array) $result;
            /** @var SyncEntityInterface $entity */
            foreach ($result as &$entity) {
                $entity = $entity->toArrayWith($rules);
            }
            $count = count($result);
            if ($this->EntityId !== null) {
                $result = array_shift($result);
            }

            echo Json::prettyPrint($result) . \PHP_EOL;
        } else {
            $count = 0;
            foreach ($result as $entity) {
                echo Json::prettyPrint($entity->toArrayWith($rules)) . \PHP_EOL;
                $count++;
            }
        }

        Console::summary(Inflect::format(
            $count,
            '{{#}} {{#:entity}} retrieved',
        ));
    }
}
