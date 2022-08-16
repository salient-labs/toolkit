<?php

declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\CliCommand;
use Lkrms\Exception\InvalidCliArgumentException;
use Lkrms\Cli\CliOptionType;
use Lkrms\Console\Console;
use Lkrms\Sync\Provider\HttpSyncProvider;
use Lkrms\Sync\SyncEntity;
use Lkrms\Util\Composer;
use Lkrms\Util\Convert;
use Lkrms\Util\Env;
use Lkrms\Util\Test;
use RuntimeException;

/**
 * Generates SyncEntity subclasses from sample entities
 *
 * Environment variables:
 * - `DEFAULT_NAMESPACE`
 * - `PHPDOC_PACKAGE`
 * - `DEFAULT_PROVIDER`
 * - `PROVIDER_NAMESPACE`
 *
 */
class GenerateSyncEntityClass extends CliCommand
{
    public static $EntityName;

    protected function _getDescription(): string
    {
        return "Generate an entity class";
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
                "description" => "A short description of the entity",
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
                "long"          => "visibility",
                "short"         => "v",
                "valueName"     => "KEYWORD",
                "description"   => "The visibility of the entity's properties",
                "optionType"    => CliOptionType::ONE_OF,
                "allowedValues" => ["public", "protected", "private"],
                "defaultValue"  => "protected",
            ],
            [
                "long"        => "json",
                "short"       => "j",
                "valueName"   => "FILE",
                "description" => "The path to a JSON-serialized sample entity",
                "optionType"  => CliOptionType::VALUE,
            ],
            [
                "long"        => "provider",
                "short"       => "i",
                "valueName"   => "CLASS",
                "description" => "The HttpSyncProvider class to retrieve a sample entity from",
                "optionType"  => CliOptionType::VALUE,
                "env"         => "DEFAULT_PROVIDER",
            ],
            [
                "long"        => "endpoint",
                "short"       => "e",
                "valueName"   => "PATH",
                "description" => "The endpoint to retrieve a sample entity from, e.g. '/user'",
                "optionType"  => CliOptionType::VALUE,
            ],
        ];
    }

    protected function _run(string ...$args)
    {
        $namespace  = explode("\\", trim($this->getOptionValue("class"), "\\"));
        $class      = array_pop($namespace);
        $namespace  = implode("\\", $namespace) ?: Env::get("DEFAULT_NAMESPACE", "");
        $fqcn       = $namespace ? $namespace . "\\" . $class : $class;
        $package    = $this->getOptionValue("package");
        $desc       = $this->getOptionValue("desc");
        $extends    = SyncEntity::class;
        $props      = ["Id" => "int|string|null"];
        $visibility = $this->getOptionValue("visibility");
        $entity     = null;
        $entityUri  = null;

        if (!$fqcn)
        {
            throw new InvalidCliArgumentException("invalid class: $fqcn");
        }

        if (!($json = $this->getOptionValue("json")) && ($providerClass = $this->getOptionValue("provider")))
        {
            if (!class_exists($providerClass) &&
                !(strpos($providerClass, "\\") === false &&
                    ($providerNamespace         = Env::get("PROVIDER_NAMESPACE", "")) &&
                    class_exists($providerClass = $providerNamespace . "\\" . $providerClass)))
            {
                throw new InvalidCliArgumentException("class does not exist: $providerClass");
            }

            $provider = $this->container()->get($providerClass);

            if ($provider instanceof HttpSyncProvider)
            {
                $endpoint  = $this->getOptionValue("endpoint") ?: "/" . Convert::toKebabCase($class);
                $entity    = $provider->getCurler($endpoint)->getJson();
                $entityUri = $provider->getEndpointUrl($endpoint);
            }
            else
            {
                throw new InvalidCliArgumentException("not a subclass of HttpSyncProvider: $providerClass");
            }
        }
        elseif ($json)
        {
            if ($json == "-")
            {
                $json = "php://stdin";
            }
            else
            {
                if (($json = realpath($json)) === false)
                {
                    throw new InvalidCliArgumentException("file not found: " . $this->getOptionValue("json"));
                }
                elseif (strpos($json, $this->container()->BasePath) === 0)
                {
                    $entityUri = "./" . ltrim(str_replace($this->container()->BasePath, "", $json), "/");
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

        if ($entity)
        {
            if (is_array($entity["data"] ?? null))
            {
                $entity = $entity["data"];
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
                protected static function getRemovablePrefixes(): ?array
                {
                    return [GenerateSyncEntityClass::$EntityName];
                }
            };

            self::$EntityName = $class;

            foreach ($entity as $key => $value)
            {
                if (is_string($key) && preg_match('/^[[:alpha:]]/', $key))
                {
                    $key = $entityClass::normaliseProperty($key);
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
            $ignore  = ["--stdout", "--force"];
            $replace = [];
            $with    = [];
            if ($json)
            {
                $ignore[]  = $this->getEffectiveArgument("provider", true);
                $ignore[]  = $this->getEffectiveArgument("endpoint", true);
                $replace[] = '/^--json=.*/';
                $with[]    = "--json=" . escapeshellarg(basename($entityUri ?: $this->getOptionValue("json")));
            }
            else
            {
                $ignore[] = $this->getEffectiveArgument("json", true);
            }
            $ignore     = array_filter($ignore, fn($arg) => !is_null($arg));
            $docBlock[] = " * @lkrms-generate-command " . implode(" ", array_map(
                fn($arg) => preg_replace($replace, $with, $arg),
                array_diff($this->getEffectiveCommandLine(true), $ignore)
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
            ($docBlock ? implode(PHP_EOL, $docBlock) . PHP_EOL : "") .
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
            if ($visibility == "protected")
            {
                $lines[] = $indent . " * @internal";
            }
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

            if ($dir = Composer::getNamespacePath($namespace))
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
