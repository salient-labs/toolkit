<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\CliOption;
use Lkrms\Facade\Sync;
use Lkrms\Iterator\Contract\FluentIteratorInterface;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Utility\Convert;

/**
 * Generates provider interfaces for sync entities
 */
class GenerateSyncProvider extends GenerateCommand
{
    private const OPERATIONS = [
        'create',
        'get',
        'update',
        'delete',
        'create-list',
        'get-list',
        'update-list',
        'delete-list',
    ];

    private const DEFAULT_OPERATIONS = [
        'create',
        'get',
        'update',
        'delete',
        'get-list',
    ];

    private ?string $ClassFqcn;

    private ?bool $Magic;

    /**
     * @var string[]|null
     */
    private ?array $Operations;

    private ?string $Plural;

    public function description(): string
    {
        return 'Generate a provider interface for a sync entity class';
    }

    protected function getOptionList(): array
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
                ->long('magic')
                ->short('g')
                ->description('Generate `@method` tags instead of declarations')
                ->bindTo($this->Magic),
            CliOption::build()
                ->long('op')
                ->short('o')
                ->valueName('operation')
                ->description('A sync operation to include in the interface')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(self::OPERATIONS)
                ->multipleAllowed()
                ->defaultValue(self::DEFAULT_OPERATIONS)
                ->valueCallback(fn(array $value) => array_intersect(self::OPERATIONS, $value))
                ->bindTo($this->Operations),
            CliOption::build()
                ->long('plural')
                ->short('l')
                ->valueName('plural')
                ->description('Specify the plural form of <class>')
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->Plural),
            ...$this->getOutputOptionList('interface'),
        ];
    }

    protected function run(string ...$args)
    {
        // Ensure sync namespaces are loaded
        if (!Sync::isLoaded()) {
            Sync::load();
        }

        $this->reset();

        $fqcn = $this->getRequiredFqcnOptionValue(
            'class',
            $this->ClassFqcn,
            null,
            $class
        );

        $operationMap = [
            'create' => SyncOperation::CREATE,
            'get' => SyncOperation::READ,
            'update' => SyncOperation::UPDATE,
            'delete' => SyncOperation::DELETE,
            'create-list' => SyncOperation::CREATE_LIST,
            'get-list' => SyncOperation::READ_LIST,
            'update-list' => SyncOperation::UPDATE_LIST,
            'delete-list' => SyncOperation::DELETE_LIST,
        ];

        if (!$fqcn) {
            throw new CliInvalidArgumentsException(
                sprintf('invalid class: %s', $fqcn),
            );
        }

        if (!is_a($fqcn, ISyncEntity::class, true)) {
            throw new CliInvalidArgumentsException(
                sprintf(
                    'does not implement %s: %s',
                    ISyncEntity::class,
                    $fqcn,
                ),
            );
        }

        $this->getRequiredFqcnOptionValue(
            'interface',
            SyncIntrospector::entityToProvider($fqcn),
            null,
            $interface,
            $namespace
        );

        $this->OutputClass = $interface;
        $this->OutputNamespace = $namespace;

        $service = $this->getFqcnAlias($fqcn, $class);
        $extends = $this->getFqcnAlias(ISyncProvider::class);

        $camelClass = Convert::toCamelCase($class);

        $desc = $this->OutputDescription === null
            ? "Syncs $class objects with a backend"
            : $this->OutputDescription;
        $ops = array_map(
            function ($op) use ($operationMap) { return $operationMap[$op]; },
            $this->Operations
        );

        $plural = $this->Plural === null ? $fqcn::plural() : $this->Plural;

        if (strcasecmp($class, $plural)) {
            $camelPlural = Convert::toCamelCase($plural);
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
        $lines = [];
        foreach ($ops as $op) {
            // CREATE and UPDATE have the same signature, so it's a good default
            if (SyncOperation::isList($op)) {
                $paramDoc = 'iterable<' . $service . '> $' . $camelPlural;
                $paramCode = 'iterable $' . $camelPlural;
                if ($this->Magic) {
                    $iterator = $this->getFqcnAlias(FluentIteratorInterface::class);
                    $returnDoc = $iterator . '<array-key,' . $service . '>';
                    $returnCode = $iterator;
                } else {
                    $returnDoc = 'iterable<' . $service . '>';
                    $returnCode = 'iterable';
                }
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

            $context = $this->getFqcnAlias(ISyncContext::class) . ' $ctx';
            $separator = $paramCode ? ', ' : '';
            $paramCode = "$context$separator$paramCode";

            if (!$this->Magic) {
                $_lines = [
                    '/**',
                    " * @param $paramDoc",
                    " * @return $returnDoc",
                    ' */',
                    "public function {$opMethod[$op]}($paramCode): $returnCode;",
                ];
                if (!$paramDoc || (!SyncOperation::isList($op) && $op !== SyncOperation::READ)) {
                    unset($_lines[1]);
                }
                if (!SyncOperation::isList($op)) {
                    unset($_lines[2]);
                }
                array_push($lines, ...array_map(fn($line) => '    ' . $line, $_lines), ...['']);
            } else {
                $methods[] = " * @method $returnDoc {$opMethod[$op]}($context$separator$paramDoc)";
            }
        }
        if (end($lines) === '') {
            array_pop($lines);
        }
        $lines[] = '}';
        $methods = implode(PHP_EOL, $methods);

        $imports = $this->generateImports();

        $docBlock[] = '/**';
        if ($desc) {
            $docBlock[] = " * $desc";
            $docBlock[] = ' *';
        }
        if ($methods) {
            $docBlock[] = $methods;
            $docBlock[] = ' *';
        }
        if (!$this->NoMetaTags) {
            $command = $this->getEffectiveCommandLine(true, [
                'stdout' => null,
                'force' => null,
            ]);
            $program = array_shift($command);
            $docBlock[] = ' * @generated by ' . $program;
            $docBlock[] = ' * @salient-generate-command ' . implode(' ', $command);
        }
        $docBlock[] = ' */';
        if (count($docBlock) == 2) {
            $docBlock = null;
        }

        $blocks = [
            '<?php declare(strict_types=1);',
            "namespace $namespace;",
            implode(PHP_EOL, $imports),
            ($docBlock ? implode(PHP_EOL, $docBlock) . PHP_EOL : '')
                . "interface $interface extends $extends" . PHP_EOL
                . '{'
        ];

        if (!$imports) {
            unset($blocks[3]);
        }

        array_unshift($lines, implode(PHP_EOL . PHP_EOL, $blocks));

        $this->handleOutput($lines);
    }
}
