<?php

declare(strict_types=1);

/**
 * @package Lkrms\LkUtil
 */

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\Test;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncEntity;
use RuntimeException;

/**
 * Generates SyncEntity subclasses from sample entities
 *
 */
class GenerateSyncEntityClass extends GenerateCommand
{
    public function getDescription(): string
    {
        return "Generate a sync entity class";
    }

    protected function getOptionList(): array
    {
        return [
            (CliOption::build()
                ->long("generate")
                ->short("g")
                ->valueName("CLASS")
                ->description("The class to generate")
                ->optionType(CliOptionType::VALUE)
                ->required()
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value))
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
                ->description("A short description of the entity")
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
                ->long("visibility")
                ->short("v")
                ->valueName("KEYWORD")
                ->description("The visibility of the entity's properties")
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(["public", "protected", "private"])
                ->defaultValue("public")
                ->go()),
            (CliOption::build()
                ->long("json")
                ->short("j")
                ->valueName("FILE")
                ->description("The path to a JSON-serialized sample entity")
                ->optionType(CliOptionType::VALUE)
                ->go()),
            (CliOption::build()
                ->long("provider")
                ->short("i")
                ->valueName("CLASS")
                ->description("The HttpSyncProvider class to retrieve a sample entity from")
                ->optionType(CliOptionType::VALUE)
                ->envVariable("DEFAULT_PROVIDER")
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value))
                ->go()),
            (CliOption::build()
                ->long("endpoint")
                ->short("e")
                ->valueName("PATH")
                ->description("The endpoint to retrieve a sample entity from, e.g. '/user'")
                ->optionType(CliOptionType::VALUE)
                ->go()),
            (CliOption::build()
                ->long("method")
                ->short("h")
                ->valueName("METHOD")
                ->description("The HTTP method to use when requesting a sample entity")
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(["get", "post"])
                ->defaultValue("get")
                ->go()),
        ];
    }

    protected function run(string ...$args)
    {
        $namespace   = explode("\\", ltrim($this->getOptionValue("generate"), "\\"));
        $class       = array_pop($namespace);
        $namespace   = implode("\\", $namespace) ?: Env::get("DEFAULT_NAMESPACE", "");
        $fqcn        = $namespace ? $namespace . "\\" . $class : $class;
        $classPrefix = $namespace ? "\\" : "";

        $extendsFqcn = SyncEntity::class;

        $this->OutputClass     = $class;
        $this->OutputNamespace = $namespace;
        $this->ClassPrefix     = $classPrefix;

        $extends = $this->getFqcnAlias($extendsFqcn);

        $package    = $this->getOptionValue("package");
        $desc       = $this->getOptionValue("desc");
        $visibility = $this->getOptionValue("visibility");
        $json       = $this->getOptionValue("json");
        $providerClass = $this->getOptionValue("provider");
        $props         = ["Id" => "int|string|null"];
        $entity        = null;
        $entityUri     = null;

        if (!$fqcn)
        {
            throw new CliArgumentsInvalidException("invalid class: $fqcn");
        }

        if ($json)
        {
            if ($json == "-")
            {
                $json = "php://stdin";
            }
            else
            {
                if (($json = realpath($json)) === false)
                {
                    throw new CliArgumentsInvalidException("file not found: " . $this->getOptionValue("json"));
                }
                elseif (strpos($json, $this->app()->BasePath) === 0)
                {
                    $entityUri = "./" . ltrim(substr($json, strlen($this->app()->BasePath)), "/");
                }
                else
                {
                    $entityUri = $json;
                }
            }

            $entity = json_decode(file_get_contents($json), true);

            if (is_null($entity))
            {
                throw new RuntimeException("Could not decode $json");
            }
        }
        elseif ($providerClass)
        {
            if (!class_exists($providerClass) &&
                !(strpos($providerClass, "\\") === false &&
                    ($providerNamespace         = Env::get("PROVIDER_NAMESPACE", "")) &&
                    class_exists($providerClass = $providerNamespace . "\\" . $providerClass)))
            {
                throw new CliArgumentsInvalidException("class does not exist: $providerClass");
            }

            $provider = $this->app()->get($providerClass);

            if ($provider instanceof HttpSyncProvider)
            {
                $endpoint  = $this->getOptionValue("endpoint") ?: "/" . Convert::toKebabCase($class);
                $method    = $this->getOptionValue("method");
                $entity    = $provider->getCurler($endpoint)->{$method}();
                $entityUri = $provider->getEndpointUrl($endpoint);
            }
            else
            {
                throw new CliArgumentsInvalidException("not a subclass of HttpSyncProvider: $providerClass");
            }
        }

        if ($entity)
        {
            foreach (["data", "Result"] as $prop)
            {
                if (is_array($entity[$prop] ?? null))
                {
                    $entity = $entity[$prop];
                    break;
                }
            }

            if (Test::isListArray($entity))
            {
                $entity = $entity[0];
            }

            $typeMap = [
                "boolean" => "bool",
                "integer" => "int",
                "double"  => "float",
                "NULL"    => "mixed",
            ];

            $entityClass = new class extends SyncEntity
            {
                /**
                 * @var string
                 */
                public static $EntityName;

                protected static function getRemovablePrefixes(): ?array
                {
                    return [self::$EntityName];
                }
            };

            $entityClass::$EntityName = $class;
            $normaliser = $entityClass::normaliser();

            foreach ($entity as $key => $value)
            {
                if (is_string($key) && preg_match('/^[[:alpha:]]/', $key))
                {
                    $key = $normaliser($key);
                    $key = Convert::toPascalCase($key);

                    // Don't limit `Id` to one type
                    if (array_key_exists($key, $props))
                    {
                        continue;
                    }

                    $type  = gettype($value);
                    $type  = $typeMap[$type] ?? $type;
                    $type .= $type == "mixed" ? "" : "|null";

                    $props[$key] = $type;
                }
            }
        }

        $imports = $this->getImports();

        $docBlock[] = "/**";
        if ($desc)
        {
            $docBlock[] = " * $desc";
            $docBlock[] = " *";
        }
        if ($visibility == "protected")
        {
            foreach ($props as $prop => $type)
            {
                $docBlock[] = " * @property $type \$$prop";
            }
            $docBlock[] = " *";
        }
        if ($package)
        {
            $docBlock[] = " * @package $package";
        }
        if (!$this->getOptionValue("no-meta"))
        {
            if ($entityUri)
            {
                $docBlock[] = " * @lkrms-sample-entity $entityUri";
            }
            $ignore = ["--stdout", "--force", $this->getEffectiveArgument("json", true)];
            $add    = [];
            if ($json)
            {
                $ignore[] = $this->getEffectiveArgument("provider", true);
                $ignore[] = $this->getEffectiveArgument("endpoint", true);
                $add[]    = "--json=" . escapeshellarg(basename($entityUri ?: $this->getOptionValue("json")));
            }
            $ignore     = array_filter($ignore);
            $docBlock[] = " * @lkrms-generate-command " . implode(" ",
                array_merge(
                    array_diff($this->getEffectiveCommandLine(true), $ignore),
                    $add
                ));
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
            "class $class extends $extends" . PHP_EOL .
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

        $lines = [implode(PHP_EOL . PHP_EOL, $blocks)];

        foreach ($props as $prop => $type)
        {
            $_lines = [
                "/**",
                " * @var $type",
                " */",
                "$visibility \$$prop;",
            ];
            array_push($lines, ...array_map(fn($line) => "    " . $line, $_lines), ... [""]);
        }

        $lines[] = "}";

        $this->handleOutput($class, $namespace, $lines);
    }
}
