<?php

declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOptionType;
use Lkrms\Console\Console;
use Lkrms\Exception\InvalidCliArgumentException;
use Lkrms\Sync\Provider\ISyncProvider;
use Lkrms\Sync\SyncEntity;
use Lkrms\Sync\SyncOperation;
use Lkrms\Util\Composer;
use Lkrms\Util\Convert;
use Lkrms\Util\Env;

/**
 * Generates provider interfaces for SyncEntity subclasses
 *
 * Environment variables:
 * - `DEFAULT_NAMESPACE`
 * - `PHPDOC_PACKAGE`
 *
 */
class GenerateSyncEntityInterface extends CliCommand
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
        return "Generate a provider interface for an entity class";
    }

    protected function _getOptions(): array
    {
        return [
            [
                "long"        => "class",
                "short"       => "c",
                "valueName"   => "CLASS",
                "description" => "The SyncEntity subclass to generate a provider for",
                "optionType"  => CliOptionType::VALUE,
                "required"    => true,
            ],
            [
                "long"        => "package",
                "short"       => "p",
                "valueName"   => "PACKAGE",
                "description" => "The PHPDoc package",
                "optionType"  => CliOptionType::VALUE,
                "env"         => "PHPDOC_PACKAGE",
            ],
            [
                "long"        => "desc",
                "short"       => "d",
                "valueName"   => "DESCRIPTION",
                "description" => "A short description of the interface",
                "optionType"  => CliOptionType::VALUE,
            ],
            [
                "long"        => "stdout",
                "short"       => "s",
                "description" => "Write to standard output",
            ],
            [
                "long"        => "force",
                "short"       => "f",
                "description" => "Overwrite the class file if it already exists",
            ],
            [
                "long"        => "no-meta",
                "short"       => "m",
                "description" => "Suppress '@lkrms-*' metadata tags",
            ],
            [
                "long"            => "op",
                "short"           => "o",
                "valueName"       => "OPERATION",
                "description"     => "A sync operation to include in the interface (may be used more than once)",
                "optionType"      => CliOptionType::ONE_OF,
                "allowedValues"   => self::OPERATIONS,
                "multipleAllowed" => true,
                "defaultValue"    => self::DEFAULT_OPERATIONS,
            ],
            [
                "long"        => "nullable-get",
                "short"       => "n",
                "description" => "Allow passing null identifiers to the 'get' operation",
            ],
            [
                "long"        => "plural",
                "short"       => "l",
                "valueName"   => "PLURAL",
                "description" => "Specify the plural form of CLASS",
                "optionType"  => CliOptionType::VALUE,
            ],
        ];
    }

    protected function _run(string ...$args)
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

        $namespace  = explode("\\", trim($this->getOptionValue("class"), "\\"));
        $class      = array_pop($namespace);
        $namespace  = implode("\\", $namespace) ?: Env::get("DEFAULT_NAMESPACE", "");
        $fqcn       = $namespace ? $namespace . "\\" . $class : $class;
        $package    = $this->getOptionValue("package");
        $desc       = $this->getOptionValue("desc");
        $desc       = is_null($desc) ? "Synchronises $class objects with a backend" : $desc;
        $interface  = $class . "Provider";
        $extends    = ISyncProvider::class;
        $camelClass = Convert::toCamelCase($class);
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

        $docBlock[] = "/**";
        if ($desc)
        {
            $docBlock[] = " * $desc";
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
            ($docBlock ? implode(PHP_EOL, $docBlock) . PHP_EOL : "") .
            "interface $interface extends \\$extends" . PHP_EOL .
            "{"
        ];

        if (!$namespace)
        {
            unset($blocks[2]);
        }

        $lines = [implode(PHP_EOL . PHP_EOL, $blocks)];

        $indent = "    ";

        foreach ($operations as $op)
        {
            // CREATE and UPDATE have the same signature, so it's a good default
            if (SyncOperation::isList($op))
            {
                $paramDoc   = "iterable<" . $class . '> $' . $camelPlural;
                $paramCode  = 'iterable $' . $camelPlural;
                $returnDoc  = "iterable<" . $class . ">";
                $returnCode = "iterable";
            }
            else
            {
                $paramDoc   = $class . ' $' . $camelClass;
                $paramCode  = $paramDoc;
                $returnDoc  = $class;
                $returnCode = $class;
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
                    $returnDoc  = "null|" . $returnDoc;
                    $returnCode = "?" . $returnCode;
                    break;

                case SyncOperation::READ_LIST:
                    $paramDoc = $paramCode = "";
                    break;
            }

            $lines[] = $indent . "/**";

            if ($paramDoc)
            {
                $lines[] = $indent . " * @param $paramDoc";
            }

            $lines[] = $indent . " * @return $returnDoc";
            $lines[] = $indent . " */";
            $lines[] = $indent . "public function {$opMethod[$op]}($paramCode): $returnCode;";
            $lines[] = "";
        }

        $lines[] = "}";

        $verb = "Creating";

        if ($this->getOptionValue("stdout"))
        {
            $file = "php://stdout";
            $verb = null;
        }
        else
        {
            $file = "$interface.php";

            if ($classFile = Composer::getClassPath($fqcn))
            {
                $file = dirname($classFile) . DIRECTORY_SEPARATOR . $file;
            }

            if (file_exists($file))
            {
                if (!$this->getOptionValue("force"))
                {
                    Console::warn("File already exists:", $file);
                    $file = preg_replace('/\.php$/', ".generated.php", $file);
                }
                if (file_exists($file))
                {
                    $verb = "Replacing";
                }
            }
        }

        if ($verb)
        {
            Console::info($verb, $file);
        }

        file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);
    }
}
