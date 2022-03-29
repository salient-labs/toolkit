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
use Lkrms\Sync\Provider\HttpSyncProvider;
use Lkrms\Sync\SyncEntity;
use Lkrms\Test;
use RuntimeException;

/**
 * Generates SyncEntity subclasses from sample entities
 *
 * Environment variables:
 * - `SYNC_ENTITY_NAMESPACE`
 * - `SYNC_ENTITY_PACKAGE`
 * - `SYNC_ENTITY_PROVIDER`
 * - `SYNC_PROVIDER_NAMESPACE`
 *
 * @package Lkrms\Util
 */
class GenerateSyncEntityClass extends CliCommand
{
    public function getDescription(): string
    {
        return "Generate an entity class";
    }

    protected function _getName(): array
    {
        return ["generate", "sync-entity"];
    }

    protected function _getOptions(): array
    {
        return [
            [
                "long"        => "class",
                "short"       => "c",
                "valueName"   => "CLASS",
                "description" => "The fully-qualified class name",
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
                "description" => "A short description of the entity",
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
                "long"          => "visibility",
                "short"         => "v",
                "valueName"     => "KEYWORD",
                "description"   => "The default visibility of each property",
                "optionType"    => CliOptionType::ONE_OF,
                "allowedValues" => ["public", "protected", "private"],
                "defaultValue"  => "public",
            ], [
                "long"        => "json",
                "short"       => "j",
                "valueName"   => "FILE",
                "description" => "The path to a JSON-serialized sample entity",
                "optionType"  => CliOptionType::VALUE,
            ], [
                "long"        => "api-provider",
                "valueName"   => "CLASS",
                "description" => "The HttpSyncProvider class to retrieve a sample entity from",
                "optionType"  => CliOptionType::VALUE,
            ], [
                "long"        => "api-path",
                "valueName"   => "PATH",
                "description" => "The endpoint to retrieve a sample entity from, e.g. '/user'",
                "optionType"  => CliOptionType::VALUE,
            ],
        ];
    }

    protected function run(...$args)
    {
        $namespace  = explode("\\", trim($this->getOptionValue("class"), "\\"));
        $class      = array_pop($namespace);
        $vendor     = reset($namespace) ?: "";
        $namespace  = implode("\\", $namespace) ?: Env::get("SYNC_ENTITY_NAMESPACE", "");
        $fqcn       = $namespace ? $namespace . "\\" . $class : $class;
        $package    = $this->getOptionValue("package") ?: Env::get("SYNC_ENTITY_PACKAGE", $vendor ?: $class);
        $desc       = $this->getOptionValue("desc");
        $extends    = SyncEntity::class;
        $props      = ["Id" => "int|string"];
        $visibility = $this->getOptionValue("visibility");
        $entity     = null;

        if (!$fqcn)
        {
            throw new CliInvalidArgumentException("invalid class: $fqcn");
        }

        if ($apiClass = $this->getOptionValue("api-provider") ?: Env::get("SYNC_ENTITY_PROVIDER", ""))
        {
            if (!class_exists($apiClass) &&
                !(strpos($apiClass, "\\") === false && ($apiNamespace = Env::get("SYNC_PROVIDER_NAMESPACE", "")) &&
                    class_exists($apiClass = $apiNamespace . "\\" . $apiClass)))
            {
                throw new CliInvalidArgumentException("class does not exist: $apiClass");
            }

            $api = new $apiClass();

            if ($api instanceof HttpSyncProvider)
            {
                $entity = $api->getCurler(
                    $this->getOptionValue("api-path") ?: "/" . Convert::toKebabCase($class)
                )->GetJson();
            }
            else
            {
                throw new CliInvalidArgumentException("not a subclass of HttpSyncProvider: $apiClass");
            }
        }
        elseif ($json = $this->getOptionValue("json"))
        {
            if ($json == "-")
            {
                $json = "php://stdin";
            }
            elseif (!file_exists($json))
            {
                throw new CliInvalidArgumentException("file not found: $json");
            }

            $entity = json_decode(file_get_contents($json), true);

            if (is_null($entity))
            {
                throw new RuntimeException("Could not decode $json");
            }
        }

        if ($entity)
        {
            if (Test::isListArray($entity))
            {
                $entity = $entity[0] ?? [];
            }

            $typeMap = [
                "boolean" => "bool",
                "integer" => "int",
                "double"  => "float",
                "NULL"    => "mixed",
            ];

            foreach ($entity as $key => $value)
            {
                if (is_string($key) && preg_match('/^[[:alpha:]]/', $key))
                {
                    $key  = Convert::toPascalCase($key);
                    $type = gettype($value);
                    $type = $typeMap[$type] ?? $type;

                    $props[$key] = $type;
                }
            }
        }

        $docBlock = [
            "/**",
            " * $desc",
            " *",
            " * @package $package",
            " */",
        ];

        if (!$desc)
        {
            unset($docBlock[1]);
        }

        $blocks = [
            "<?php",
            "declare(strict_types=1);",
            "namespace $namespace;",
            implode(PHP_EOL, $docBlock) . PHP_EOL .
            "class $class extends \\$extends" . PHP_EOL .
            "{"
        ];

        if (!$namespace)
        {
            unset($blocks[2]);
        }

        $lines = [implode(PHP_EOL . PHP_EOL, $blocks)];

        $indent = "    ";

        foreach ($props as $prop => $type)
        {
            $lines[] = $indent . "/**";
            $lines[] = $indent . " * @var $type";
            $lines[] = $indent . " */";
            $lines[] = $indent . "$visibility \$$prop;";
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
            $file = "$class.php";

            if ($dir = File::getNamespacePath($namespace))
            {
                if (!is_dir($dir))
                {
                    mkdir($dir, 0777, true);
                }

                $file = $dir . DIRECTORY_SEPARATOR . $file;
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

