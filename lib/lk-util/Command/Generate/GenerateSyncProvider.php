<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\LkUtil\Catalog\EnvVar;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncIntrospector;

/**
 * Generates provider interfaces for SyncEntity subclasses
 *
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
                ->description('The SyncEntity subclass to generate a provider for')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value, Env::get(EnvVar::NS_DEFAULT, '')))
                ->required(),
            CliOption::build()
                ->long('extend')
                ->short('x')
                ->valueName('class')
                ->description('An interface to extend (must extend ISyncProvider)')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->valueCallback(fn(array $values) => $this->getMultipleFqcnOptionValue($values, Env::get(EnvVar::NS_DEFAULT, ''))),
            CliOption::build()
                ->long('magic')
                ->short('v')
                ->description('Generate @method tags instead of declarations'),
            ...$this->getOutputOptionList('interface'),
            CliOption::build()
                ->long('op')
                ->short('o')
                ->valueName('operation')
                ->description('A sync operation to include in the interface')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(self::OPERATIONS)
                ->multipleAllowed()
                ->defaultValue(self::DEFAULT_OPERATIONS)
                ->valueCallback(fn(array $value) => array_intersect(self::OPERATIONS, $value)),
            CliOption::build()
                ->long('plural')
                ->short('l')
                ->valueName('plural')
                ->description('Specify the plural form of CLASS')
                ->optionType(CliOptionType::VALUE),
        ];
    }

    protected function run(string ...$args)
    {
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

        $namespace = explode('\\', $classArg = $this->getOptionValue('class'));
        $class = array_pop($namespace);
        $namespace = implode('\\', $namespace);
        $fqcn = $namespace . '\\' . $class;

        if (!$class) {
            throw new CliInvalidArgumentsException("invalid class: $classArg");
        }

        if (!is_a($fqcn, SyncEntity::class, true)) {
            throw new CliInvalidArgumentsException("not a subclass of SyncEntity: $classArg");
        }

        $namespace = explode('\\', SyncIntrospector::entityToProvider($fqcn));
        $interface = array_pop($namespace);
        $namespace = implode('\\', $namespace);

        $extendsFqcn = [];
        foreach ($this->getOptionValue('extend') ?: [ISyncProvider::class] as $_extends) {
            $extendsNamespace = explode('\\', $_extends);
            $extendsClass = array_pop($extendsNamespace);
            $extendsNamespace = implode('\\', $extendsNamespace);
            $extendsFqcn[] = $extendsNamespace . '\\' . $extendsClass;
        }

        $this->OutputClass = $interface;
        $this->OutputNamespace = $namespace;

        $service = $this->getFqcnAlias($fqcn, $class);
        $extends = [];
        foreach ($extendsFqcn as $_extends) {
            $extends[] = $this->getFqcnAlias($_extends);
        }
        $extends = implode(', ', $extends);

        $camelClass = Convert::toCamelCase($class);

        $magic = $this->getOptionValue('magic');
        $desc = $this->OutputDescription;
        $desc = is_null($desc) ? "Syncs $class objects with a backend" : $desc;
        $ops = array_map(
            function ($op) use ($operationMap) { return $operationMap[$op]; },
            $this->getOptionValue('op')
        );

        $plural = $this->getOptionValue('plural') ?: $fqcn::plural();

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

            $context = $this->getFqcnAlias(ISyncContext::class) . ' $ctx';
            $separator = $paramCode ? ', ' : '';
            $paramCode = "$context$separator$paramCode";

            if (!$magic) {
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

        $imports = $this->getImports();

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
            $docBlock[] = ' * @lkrms-generate-command '
                . implode(
                    ' ',
                    $this->getEffectiveCommandLine(true, [
                        'stdout' => null,
                        'force' => null,
                    ])
                );
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

        $this->handleOutput($interface, $namespace, $lines);
    }
}
