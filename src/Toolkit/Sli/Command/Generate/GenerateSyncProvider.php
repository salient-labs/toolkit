<?php declare(strict_types=1);

namespace Salient\Sli\Command\Generate;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Facade\Sync;
use Salient\Sync\SyncUtil;
use Salient\Utility\Arr;
use Salient\Utility\Str;

/**
 * Generates provider interfaces for sync entities
 */
class GenerateSyncProvider extends AbstractGenerateCommand
{
    private const OPERATION_MAP = [
        'create' => SyncOperation::CREATE,
        'get' => SyncOperation::READ,
        'update' => SyncOperation::UPDATE,
        'delete' => SyncOperation::DELETE,
        'create-list' => SyncOperation::CREATE_LIST,
        'get-list' => SyncOperation::READ_LIST,
        'update-list' => SyncOperation::UPDATE_LIST,
        'delete-list' => SyncOperation::DELETE_LIST,
    ];

    private const DEFAULT_OPERATIONS = [
        'create',
        'get',
        'update',
        'delete',
        'get-list',
    ];

    private string $ClassFqcn = '';
    private bool $NoMagic = false;
    /** @var string[] */
    private array $Operations = [];
    private ?string $Plural = null;

    public function getDescription(): string
    {
        return 'Generate a provider interface for a sync entity class';
    }

    protected function getOptionList(): iterable
    {
        return [
            CliOption::build()
                ->long('class')
                ->valueName('class')
                ->description('The sync entity class to generate a provider for')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required()
                ->bindTo($this->ClassFqcn),
            CliOption::build()
                ->long('no-magic')
                ->short('G')
                ->description('Generate declarations instead of `@method` tags')
                ->bindTo($this->NoMagic),
            CliOption::build()
                ->long('op')
                ->short('o')
                ->valueName('operation')
                ->description('A sync operation to include in the interface')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::OPERATION_MAP))
                ->multipleAllowed()
                ->defaultValue(self::DEFAULT_OPERATIONS)
                ->bindTo($this->Operations),
            CliOption::build()
                ->long('plural')
                ->short('l')
                ->valueName('plural')
                ->description('Specify the plural form of <class>')
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->Plural),
            ...$this->getGlobalOptionList('interface'),
        ];
    }

    protected function run(string ...$args)
    {
        // Ensure sync namespaces are loaded
        if (!Sync::isLoaded()) {
            Sync::load();
        }

        $this->startRun();

        $this->OutputType = self::GENERATE_INTERFACE;

        $fqcn = $this->requireFqcnOptionValue(
            'class',
            $this->ClassFqcn,
            null,
            $class
        );

        if (!is_a($fqcn, SyncEntityInterface::class, true)) {
            throw new CliInvalidArgumentsException(
                sprintf(
                    'does not implement %s: %s',
                    SyncEntityInterface::class,
                    $fqcn,
                ),
            );
        }

        $this->requireFqcnOptionValue(
            'interface',
            SyncUtil::getEntityTypeProvider($fqcn, SyncUtil::getStore($this->App)),
            null,
            $interface,
            $namespace
        );

        $this->OutputClass = $interface;
        $this->OutputNamespace = $namespace;

        $service = $this->getFqcnAlias($fqcn, $class);
        $context = $this->getFqcnAlias(SyncContextInterface::class);
        $this->Extends[] = $this->getFqcnAlias(SyncProviderInterface::class);

        if ($this->Description === null) {
            $this->Description = sprintf(
                'Syncs %s objects with a backend',
                $class,
            );
        }

        $ops = array_map(
            fn($op) => self::OPERATION_MAP[$op],
            array_intersect(array_keys(self::OPERATION_MAP), $this->Operations),
        );

        $camelClass = Str::camel($class);
        $plural = Str::coalesce($this->Plural ?? $fqcn::getPlural(), $class);

        if (strcasecmp($class, $plural)) {
            $camelPlural = Str::camel($plural);
            $opMethod = [
                SyncOperation::CREATE => 'create' . $class,
                SyncOperation::READ => 'get' . $class,
                SyncOperation::UPDATE => 'update' . $class,
                SyncOperation::DELETE => 'delete' . $class,
                SyncOperation::CREATE_LIST => 'create' . $plural,
                SyncOperation::READ_LIST => 'get' . $plural,
                SyncOperation::UPDATE_LIST => 'update' . $plural,
                SyncOperation::DELETE_LIST => 'delete' . $plural,
            ];
        } else {
            $camelPlural = $camelClass;
            $opMethod = [
                SyncOperation::CREATE => 'create_' . $class,
                SyncOperation::READ => 'get_' . $class,
                SyncOperation::UPDATE => 'update_' . $class,
                SyncOperation::DELETE => 'delete_' . $class,
                SyncOperation::CREATE_LIST => 'createList_' . $class,
                SyncOperation::READ_LIST => 'getList_' . $class,
                SyncOperation::UPDATE_LIST => 'updateList_' . $class,
                SyncOperation::DELETE_LIST => 'deleteList_' . $class,
            ];
        }

        $methods = [];
        foreach ($ops as $op) {
            // CREATE and UPDATE have the same signature, so it's a good default
            if (SyncUtil::isListOperation($op)) {
                $paramDoc = 'iterable<' . $service . '> $' . $camelPlural;
                $paramCode = 'iterable $' . $camelPlural;
                $returnDoc = 'iterable<' . $service . '>';
                $returnCode = 'iterable';
            } else {
                $paramDoc = $service . ' $' . $camelClass;
                $paramCode = $paramDoc;
                $returnDoc = $service;
                $returnCode = $service;
            }

            switch ($op) {
                case SyncOperation::READ:
                    $paramDoc = 'int|string|null $id';
                    $paramCode = '$id';
                    break;

                case SyncOperation::READ_LIST:
                    $paramDoc = $paramCode = '';
                    break;
            }

            if ($this->NoMagic) {
                $paramCode = Arr::whereNotEmpty([$context . ' $ctx', $paramCode]);

                $phpDoc = [];
                if ($paramDoc !== ''
                        && (SyncUtil::isListOperation($op) || $op === SyncOperation::READ)) {
                    $phpDoc[] = "@param $paramDoc";
                }
                if (SyncUtil::isListOperation($op)) {
                    $phpDoc[] = "@return $returnDoc";
                }

                $this->addMethod($opMethod[$op], null, $paramCode, $returnCode, $phpDoc, true);
            } else {
                $methods[] = sprintf(
                    '@method %s %s(%s)',
                    $returnDoc,
                    $opMethod[$op],
                    Arr::implode(', ', [$context . ' $ctx', $paramDoc]),
                );
            }
        }

        $docBlock = [];

        if ($methods) {
            array_push($docBlock, ...$methods);
            $docBlock[] = '';
        }

        if ($docBlock) {
            $this->PHPDoc = implode(\PHP_EOL, $docBlock);
        }

        $this->handleOutput($this->generate());
    }
}
