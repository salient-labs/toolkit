<?php

declare(strict_types=1);

namespace Lkrms\Util\Command\Generate;

use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliInvalidArgumentException;
use Lkrms\Cli\CliOptionType;
use Lkrms\Console\Console;
use Lkrms\Convert;
use Lkrms\Env;
use Lkrms\File;
use Lkrms\Sync\Provider\ISyncProvider;
use Lkrms\Sync\SyncEntity;
use Lkrms\Sync\SyncOperation;

/**
 * Generates provider interfaces for SyncEntity subclasses
 *
 * Environment variables:
 * - `SYNC_ENTITY_NAMESPACE`
 * - `SYNC_ENTITY_PACKAGE`
 *
 * @package Lkrms\Util
 */
class GenerateSyncEntityProviderInterface extends CliCommand
{
    public function getDescription(): string
    {
        return "Generate a provider interface for an entity class";
    }

    protected function _getName(): array
    {
        return ["generate", "sync-entity-provider"];
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
            ], [
                "long"        => "package",
                "short"       => "p",
                "valueName"   => "PACKAGE",
                "description" => "The PHPDoc package",
                "optionType"  => CliOptionType::VALUE,
            ], [
                "long"        => "desc",
                "short"       => "d",
                "valueName"   => "DESCRIPTION",
                "description" => "A short description of the interface",
                "optionType"  => CliOptionType::VALUE,
            ], [
                "long"        => "stdout",
                "short"       => "s",
                "description" => "Write to standard output",
            ], [
                "long"        => "force",
                "short"       => "f",
                "description" => "Overwrite the class file if it already exists",
            ], [
                "long"            => "op",
                "short"           => "o",
                "valueName"       => "OPERATION",
                "description"     => "A sync operation to include in the interface (may be used more than once)",
                "optionType"      => CliOptionType::ONE_OF,
                "allowedValues"   => ["create", "get", "update", "delete", "get-list"],
                "multipleAllowed" => true,
                "defaultValue"    => ["create", "get", "update", "delete", "get-list"],
            ],
        ];
    }

    protected function run(...$args)
    {
        $operationMap = [
            "create"   => SyncOperation::CREATE,
            "get"      => SyncOperation::READ,
            "update"   => SyncOperation::UPDATE,
            "delete"   => SyncOperation::DELETE,
            "get-list" => SyncOperation::READ_LIST,
        ];

        $namespace  = explode("\\", trim($this->getOptionValue("class"), "\\"));
        $class      = array_pop($namespace);
        $vendor     = reset($namespace) ?: "";
        $namespace  = implode("\\", $namespace) ?: Env::get("SYNC_ENTITY_NAMESPACE", "");
        $fqcn       = $namespace ? $namespace . "\\" . $class : $class;
        $package    = $this->getOptionValue("package") ?: Env::get("SYNC_ENTITY_PACKAGE", $vendor ?: $class);
        $desc       = $this->getOptionValue("desc") ?: "Synchronises $class objects with a backend";
        $interface  = $class . "Provider";
        $extends    = ISyncProvider::class;
        $camelClass = Convert::toCamelCase($class);
        $operations = array_map(
            function ($op) use ($operationMap) { return $operationMap[$op]; },
            $this->getOptionValue("op")
        );

        if (!$fqcn)
        {
            throw new CliInvalidArgumentException("invalid class: $fqcn");
        }

        if (!is_a($fqcn, SyncEntity::class, true))
        {
            throw new CliInvalidArgumentException("not a subclass of SyncEntity: $fqcn");
        }

        $plural = $fqcn::getPlural();

        if ($plural != $class)
        {
            $opMethod = [
                SyncOperation::CREATE    => "create" . $class,
                SyncOperation::READ      => "get" . $class,
                SyncOperation::UPDATE    => "update" . $class,
                SyncOperation::DELETE    => "delete" . $class,
                SyncOperation::READ_LIST => "get" . $plural,
            ];
        }
        else
        {
            $opMethod = [
                SyncOperation::CREATE    => "create_" . $class,
                SyncOperation::READ      => "get_" . $class,
                SyncOperation::UPDATE    => "update_" . $class,
                SyncOperation::DELETE    => "delete_" . $class,
                SyncOperation::READ_LIST => "getList_" . $class,
            ];
        }

        $docBlock = [
            "/**",
            " * $desc",
            " *",
            " * @package $package",
            " */",
        ];

        $blocks = [
            "<?php",
            "declare(strict_types=1);",
            "namespace $namespace;",
            implode(PHP_EOL, $docBlock) . PHP_EOL .
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
            $paramDoc   = $class . ' $' . $camelClass;
            $paramCode  = $paramDoc;
            $returnDoc  = $class;
            $returnCode = $class;

            switch ($op)
            {
                case SyncOperation::READ:

                    $paramDoc  = 'int|string|null $id';
                    $paramCode = '$id = null';

                    break;

                case SyncOperation::DELETE:

                    $returnDoc  = "null|" . $class;
                    $returnCode = "?" . $class;

                    break;

                case SyncOperation::READ_LIST:

                    $paramDoc   = $paramCode = "";
                    $returnDoc .= "[]";
                    $returnCode = "array";

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

            if ($classFile = File::getClassPath($fqcn))
            {
                $file = dirname($classFile) . DIRECTORY_SEPARATOR . $file;
            }

            if (file_exists($file))
            {
                if (!$this->getOptionValue("force"))
                {
                    Console::Warn("File already exists:", $file);
                    $file = preg_replace('/\.php$/', ".generated.php", $file);
                }
                else
                {
                    $verb = "Replacing";
                }
            }
        }

        if ($verb)
        {
            Console::Info($verb, $file);
        }

        file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);
    }
}

