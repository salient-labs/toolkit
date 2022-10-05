<?php

declare(strict_types=1);

/**
 * @package Lkrms\LkUtil
 */

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Exception\InvalidCliArgumentException;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncOperation;

/**
 * Generates provider interfaces for SyncEntity subclasses
 *
 */
class GenerateSyncEntityInterface extends GenerateCommand
{
    private const OPERATIONS = [
        "create", "get", "update", "delete",
        "create-list", "get-list", "update-list", "delete-list"
    ];

    private const DEFAULT_OPERATIONS = [
        "create", "get", "update", "delete", "get-list"
    ];

    protected function _getDescription(): string
    {
        return "Generate a provider interface for a sync entity class";
    }

    protected function _getOptions(): array
    {
        return [
            (CliOption::build()
                ->long("class")
                ->short("c")
                ->valueName("CLASS")
                ->description("The SyncEntity subclass to generate a provider for")
                ->optionType(CliOptionType::VALUE)
                ->required()
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value))
                ->go()),
            (CliOption::build()
                ->long("magic")
                ->short("v")
                ->description("Generate @method tags instead of declarations")
                ->go()),
            (CliOption::build()
                ->long("package")
                ->short("p")
                ->valueName("PACKAGE")
                ->description("The PHPDoc package")
                ->optionType(CliOptionType::VALUE)
                ->envVariable("PHPDOC_PACKAGE")
                ->go()),
            (CliOption::build()
                ->long("desc")
                ->short("d")
                ->valueName("DESCRIPTION")
                ->description("A short description of the interface")
                ->optionType(CliOptionType::VALUE)
                ->go()),
            (CliOption::build()
                ->long("stdout")
                ->short("s")
                ->description("Write to standard output")
                ->go()),
            (CliOption::build()
                ->long("force")
                ->short("f")
                ->description("Overwrite the class file if it already exists")
                ->go()),
            (CliOption::build()
                ->long("no-meta")
                ->short("m")
                ->description("Suppress '@lkrms-*' metadata tags")
                ->go()),
            (CliOption::build()
                ->long("op")
                ->short("o")
                ->valueName("OPERATION")
                ->description("A sync operation to include in the interface (may be used more than once)")
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(self::OPERATIONS)
                ->multipleAllowed()
                ->defaultValue(self::DEFAULT_OPERATIONS)
                ->go()),
            (CliOption::build()
                ->long("nullable-get")
                ->short("n")
                ->description("Allow passing null identifiers to the 'get' operation")
                ->go()),
            (CliOption::build()
                ->long("plural")
                ->short("l")
                ->valueName("PLURAL")
                ->description("Specify the plural form of CLASS")
                ->optionType(CliOptionType::VALUE)
                ->go()),
        ];
    }

    protected function run(string ...$args)
    {
        $operationMap = [
            "create"      => SyncOperation::CREATE,
            "get"         => SyncOperation::READ,
            "update"      => SyncOperation::UPDATE,
            "delete"      => SyncOperation::DELETE,
            "create-list" => SyncOperation::CREATE_LIST,
            "get-list"    => SyncOperation::READ_LIST,
            "update-list" => SyncOperation::UPDATE_LIST,
            "delete-list" => SyncOperation::DELETE_LIST,
        ];

        $namespace   = explode("\\", ltrim($this->getOptionValue("class"), "\\"));
        $class       = array_pop($namespace);
        $namespace   = implode("\\", $namespace) ?: Env::get("DEFAULT_NAMESPACE", "");
        $fqcn        = $namespace ? $namespace . "\\" . $class : $class;
        $classPrefix = $namespace ? "\\" : "";

        $interface   = $class . "Provider";
        $extendsFqcn = ISyncProvider::class;

        $this->OutputClass     = $interface;
        $this->OutputNamespace = $namespace;
        $this->ClassPrefix     = $classPrefix;

        $service = $this->getFqcnAlias($fqcn, $class);
        $extends = $this->getFqcnAlias($extendsFqcn);

        $camelClass = Convert::toCamelCase($class);

        $magic      = $this->getOptionValue("magic");
        $package    = $this->getOptionValue("package");
        $desc       = $this->getOptionValue("desc");
        $desc       = is_null($desc) ? "Syncs $class objects with a backend" : $desc;
        $operations = array_map(
            function ($op) use ($operationMap) { return $operationMap[$op]; },
            array_intersect(self::OPERATIONS, $this->getOptionValue("op"))
        );

        if (!$fqcn)
        {
            throw new InvalidCliArgumentException("invalid class: $fqcn");
        }

        if (!is_a($fqcn, SyncEntity::class, true))
        {
            throw new InvalidCliArgumentException("not a subclass of SyncEntity: $fqcn");
        }

        $plural = $this->getOptionValue("plural") ?: $fqcn::getPluralClassName();

        if (strcasecmp($class, $plural))
        {
            $camelPlural = Convert::toCamelCase($plural);
            $opMethod    = [
                SyncOperation::CREATE      => "create" . $class,
                SyncOperation::READ        => "get" . $class,
                SyncOperation::UPDATE      => "update" . $class,
                SyncOperation::DELETE      => "delete" . $class,
                SyncOperation::CREATE_LIST => "create" . $plural,
                SyncOperation::READ_LIST   => "get" . $plural,
                SyncOperation::UPDATE_LIST => "update" . $plural,
                SyncOperation::DELETE_LIST => "delete" . $plural,
            ];
        }
        else
        {
            $camelPlural = $camelClass;
            $opMethod    = [
                SyncOperation::CREATE      => "create_" . $class,
                SyncOperation::READ        => "get_" . $class,
                SyncOperation::UPDATE      => "update_" . $class,
                SyncOperation::DELETE      => "delete_" . $class,
                SyncOperation::CREATE_LIST => "createList_" . $class,
                SyncOperation::READ_LIST   => "getList_" . $class,
                SyncOperation::UPDATE_LIST => "updateList_" . $class,
                SyncOperation::DELETE_LIST => "deleteList_" . $class,
            ];
        }

        $methods = [];
        $lines   = [];
        foreach ($operations as $op)
        {
            // CREATE and UPDATE have the same signature, so it's a good default
            if (SyncOperation::isList($op))
            {
                $paramDoc   = "iterable<" . $service . '> $' . $camelPlural;
                $paramCode  = 'iterable $' . $camelPlural;
                $returnDoc  = "iterable<" . $service . ">";
                $returnCode = "iterable";
            }
            else
            {
                $paramDoc   = $service . ' $' . $camelClass;
                $paramCode  = $paramDoc;
                $returnDoc  = $service;
                $returnCode = $service;
            }

            switch ($op)
            {
                case SyncOperation::READ:
                    if ($this->getOptionValue("nullable-get"))
                    {
                        $paramDoc  = 'int|string|null $id';
                        $paramCode = '$id = null';
                    }
                    else
                    {
                        $paramDoc  = 'int|string $id';
                        $paramCode = '$id';
                    }
                    break;

                case SyncOperation::DELETE:
                case SyncOperation::DELETE_LIST:
                    $returnDoc  = $returnDoc . "|null";
                    $returnCode = "?" . $returnCode;
                    break;

                case SyncOperation::READ_LIST:
                    $paramDoc = $paramCode = "";
                    break;
            }

            if (!$magic)
            {
                $_lines = [
                    "/**",
                    " * @param $paramDoc",
                    " * @return $returnDoc",
                    " */",
                    "public function {$opMethod[$op]}($paramCode): $returnCode;",
                ];
                if (!$paramDoc)
                {
                    unset($_lines[1]);
                }
                array_push($lines, ...array_map(fn($line) => "    " . $line, $_lines), ... [""]);
            }
            else
            {
                $methods[] = " * @method $returnDoc {$opMethod[$op]}($paramDoc)";
            }
        }
        $lines[] = "}";
        $methods = implode(PHP_EOL, $methods);

        $imports = $this->getImports();

        $docBlock[] = "/**";
        if ($desc)
        {
            $docBlock[] = " * $desc";
            $docBlock[] = " *";
        }
        if ($methods)
        {
            $docBlock[] = $methods;
            $docBlock[] = " *";
        }
        if ($package)
        {
            $docBlock[] = " * @package $package";
        }
        if (!$this->getOptionValue("no-meta"))
        {
            $docBlock[] = " * @lkrms-generate-command " . implode(" ",
                array_diff($this->getEffectiveCommandLine(true),
                    ["--stdout", "--force"]));
        }
        $docBlock[] = " */";
        if (count($docBlock) == 2)
        {
            $docBlock = null;
        }

        $blocks = [
            "<?php",
            "declare(strict_types=1);",
            "namespace $namespace;",
            implode(PHP_EOL, $imports),
            ($docBlock ? implode(PHP_EOL, $docBlock) . PHP_EOL : "") .
            "interface $interface extends $extends" . PHP_EOL .
            "{"
        ];

        if (!$imports)
        {
            unset($blocks[3]);
        }

        if (!$namespace)
        {
            unset($blocks[2]);
        }

        array_unshift($lines, implode(PHP_EOL . PHP_EOL, $blocks));

        $this->handleOutput($interface, $namespace, $lines);
    }
}
