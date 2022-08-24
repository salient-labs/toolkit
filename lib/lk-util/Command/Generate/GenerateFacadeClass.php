<?php

declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOptionType;
use Lkrms\Concept\Facade;
use Lkrms\Console\Console;
use Lkrms\Exception\InvalidCliArgumentException;
use Lkrms\Support\PhpDocParser;
use Lkrms\Support\TokenExtractor;
use Lkrms\Util\Composer;
use Lkrms\Util\Convert;
use Lkrms\Util\Env;
use Lkrms\Util\Reflect;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Generates static interfaces for underlying singletons
 *
 */
class GenerateFacadeClass extends CliCommand
{
    protected function _getDescription(): string
    {
        return "Generate a facade class for a singleton class";
    }

    protected function _getOptions(): array
    {
        return [
            [
                "long"        => "class",
                "short"       => "c",
                "valueName"   => "CLASS",
                "description" => "The class to generate a facade for",
                "optionType"  => CliOptionType::VALUE,
                "required"    => true,
            ],
            [
                "long"        => "generate",
                "short"       => "g",
                "valueName"   => "CLASS",
                "description" => "The class to generate",
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
                "description" => "A short description of the facade",
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
                "long"        => "declared",
                "short"       => "e",
                "description" => "Ignore inherited methods",
            ],
        ];
    }

    public const SKIP_METHODS = [
        "getReadable",
        "getWritable",

        // IFacade displaces these if the underlying class has them
        "getInstance",
        "isLoaded",
        "load",
    ];

    private function isReservedWord(string $name)
    {
        return in_array($name, [
            "array", "bool", "callable", "enum", "false",
            "float", "int", "iterable", "mixed", "never",
            "null", "numeric", "object", "parent", "resource",
            "self", "static", "string", "true", "void",
        ]);
    }

    protected function run(string ...$args)
    {
        $namespace = explode("\\", trim($this->getOptionValue("class"), "\\"));
        $class     = array_pop($namespace);
        $namespace = implode("\\", $namespace) ?: Env::get("DEFAULT_NAMESPACE", "");
        $fqcn      = $namespace ? $namespace . "\\" . $class : $class;

        $facadeNamespace = explode("\\", trim($this->getOptionValue("generate"), "\\"));
        $facade          = array_pop($facadeNamespace);
        $facadeNamespace = implode("\\", $facadeNamespace) ?: Env::get("FACADE_NAMESPACE", $namespace);
        $facadeFqcn      = $facadeNamespace ? $facadeNamespace . "\\" . $facade : $facade;
        $classPrefix     = $facadeNamespace ? "\\" : "";

        $package  = $this->getOptionValue("package");
        $desc     = $this->getOptionValue("desc");
        $desc     = is_null($desc) ? "A facade for $class" : $desc;
        $declared = $this->getOptionValue("declared");

        $extends = trim(Facade::class, "\\");
        $service = $fqcn;

        if (!$fqcn)
        {
            throw new InvalidCliArgumentException("invalid class: $fqcn");
        }

        if (!$facadeFqcn)
        {
            throw new InvalidCliArgumentException("invalid facade: $facadeFqcn");
        }

        try
        {
            $_class = new ReflectionClass($fqcn);

            if (!$_class->isInstantiable())
            {
                throw new InvalidCliArgumentException("not an instantiable class: $fqcn");
            }
        }
        catch (ReflectionException $ex)
        {
            throw new InvalidCliArgumentException("class does not exist: $fqcn");
        }

        $files        = [];
        $maybeAddFile = (
            function ($file) use (&$files)
            {
                if ($file !== false)
                {
                    $files[$file] = $file;
                }
            }
        );

        $maybeAddFile($_class->getFileName());
        foreach (($_methods = $_class->getMethods(ReflectionMethod::IS_PUBLIC)) as $_method)
        {
            $maybeAddFile($_method->getFileName());
        }

        $useMap     = [];
        $typeMap    = [];
        $useMapFull = [
            "Facade" => ltrim(Facade::class, "\\"),
            $class   => $fqcn,
        ];
        foreach ($files as $file)
        {
            $useMap[$file]  = (new TokenExtractor($file))->getUseMap();
            $typeMap[$file] = array_change_key_case(array_flip($useMap[$file]), CASE_LOWER);
            $useMapFull     = array_merge($useMap[$file], $useMapFull);
        }
        $typeMapFull = array_change_key_case(array_flip($useMapFull), CASE_LOWER);

        $fileTypeMap = $typeMapFull;
        $use         = [];

        $typeNameCallback = function (string $name) use ($facadeNamespace, &$fileTypeMap, &$use): ?string
        {
            $name = trim($name, "\\");
            $ns   = Convert::classToNamespace($name);
            $base = Convert::classToBasename($name);
            if (!strcasecmp($ns, $facadeNamespace))
            {
                return $base;
            }
            if ((($alias = $fileTypeMap[strtolower($name)] ?? null) &&
                (!array_key_exists($alias, $use) || $name == $use[$alias])) ||
            (!$this->isReservedWord($name) && ($alias = $base) &&
                (!array_key_exists($alias, $use) || $name == $use[$alias])))
            {
                $use[$alias] = $name;
                return $alias;
            }
            return null;
        };
        $typeCallback = function (string $name) use ($typeNameCallback): string
        {
            return $typeNameCallback($name) ?: $name;
        };
        $phpDocTypeCallback = function (string $type) use (&$methodFile, &$methodNamespace, $useMap, $typeCallback): string
        {
            return preg_replace_callback(
                '/(?<!\$)\b' . PhpDocParser::TYPE_PATTERN . '\b/',
                function ($match) use (&$methodFile, &$methodNamespace, $useMap, $typeCallback)
                {
                    if (preg_match('/^\\\\/', $match[0]) ||
                        $this->isReservedWord($match[0]))
                    {
                        return $match[0];
                    }
                    return $typeCallback($useMap[$methodFile][$match[0]]
                        ?? "\\" . $methodNamespace . "\\" . $match[0]);
                },
                $type
            );
        };

        $extends = $typeCallback("$classPrefix$extends");
        $service = $typeCallback("$classPrefix$service");

        $returnTypeMap = [
            'static' => $service,
            'self'   => $service,
            '$this'  => $service,
        ];

        usort($_methods,
            fn(ReflectionMethod $a, ReflectionMethod $b) => $a->isConstructor()
            ? -1 : ($b->isConstructor()
                ? 1 : $a->getName() <=> $b->getName()));
        $methods = [];
        foreach ($_methods as $_method)
        {
            $phpDoc = (($docBlock = $_method->getDocComment())
                ? new PhpDocParser($docBlock)
                : null);
            $methodFile      = $_method->getFileName();
            $methodNamespace = $_method->getDeclaringClass()->getNamespaceName();

            if ($_method->isConstructor())
            {
                $method  = "load";
                $type    = $service;
                $summary = "Create and return the underlying $class";
            }
            else
            {
                $method = $_method->getName();
                if (strpos($method, "__") === 0 ||
                    in_array($method, self::SKIP_METHODS) ||
                    ($declared && $_method->getDeclaringClass() != $_class))
                {
                    continue;
                }

                $fileTypeMap = $typeMap[$methodFile];

                $type = (($_type = $phpDoc->Return["type"] ?? null)
                    ? $phpDocTypeCallback($_type)
                    : ($_method->hasReturnType()
                        ? Reflect::getTypeDeclaration($_method->getReturnType(), $classPrefix, $typeNameCallback)
                        : "mixed"));
                $type    = $returnTypeMap[$type] ?? $type;
                $summary = $phpDoc->Summary ?? null;

                // Work around phpDocumentor's inability to parse "?<type>"
                // return types
                if (strpos($type, "?") === 0)
                {
                    $type = substr($type, 1) . "|null";
                }
            }

            $params = [];
            foreach ($_method->getParameters() as $_param)
            {
                $params[] = Reflect::getParameterDeclaration(
                    $_param,
                    $classPrefix,
                    $typeNameCallback,
                    // Override the declared type if defined in the PHPDoc
                    (($_type = $phpDoc->Params[$_param->getName()]["type"] ?? null)
                        ? $phpDocTypeCallback($_type)
                        : null)
                );
            }

            $methods[] = (" * @method static $type $method("
                . str_replace("\n", "\n * ", implode(", ", $params)) . ")"
                . ($summary ? " $summary" : ""));

            if ($_method->isConstructor())
            {
                $methods[] = " * @method static $type getInstance() Return the underlying $class";
                $methods[] = " * @method static bool isLoaded() Return true if the underlying $class has been created";
            }
        }
        $methods = implode(PHP_EOL, $methods);

        $imports = [];
        foreach ($use as $from => $to)
        {
            if (!strcasecmp($from, Convert::classToBasename($to)))
            {
                $imports[] = "use $to;";
                continue;
            }
            $imports[] = "use $to as $from;";
        }
        sort($imports);

        unset($docBlock);
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
        $docBlock[] = " * @uses $service";
        if (!$this->getOptionValue("no-meta"))
        {
            $docBlock[] = " * @lkrms-generate-command " . implode(" ",
                array_diff($this->getEffectiveCommandLine(true),
                    ["--stdout", "--force"]));
        }
        $docBlock[] = " */";

        $blocks = [
            "<?php",
            "declare(strict_types=1);",
            "namespace $facadeNamespace;",
            implode(PHP_EOL, $imports),
            implode(PHP_EOL, $docBlock) . PHP_EOL .
            "final class $facade extends $extends" . PHP_EOL .
            "{"
        ];

        if (!$imports)
        {
            unset($blocks[3]);
        }

        if (!$facadeNamespace)
        {
            unset($blocks[2]);
        }

        $lines   = [implode(PHP_EOL . PHP_EOL, $blocks)];
        $lines[] = "    /**";
        $lines[] = "     * @internal";
        $lines[] = "     */";
        $lines[] = "    protected static function getServiceName(): string";
        $lines[] = "    {";
        $lines[] = "        return $service::class;";
        $lines[] = "    }";
        $lines[] = "}";

        $verb = "Creating";

        if ($this->getOptionValue("stdout"))
        {
            $file = "php://stdout";
            $verb = null;
        }
        else
        {
            $file = "$facade.php";

            if ($dir = Composer::getNamespacePath($facadeNamespace))
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
