<?php

declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\CliOptionType;
use Lkrms\Concept\Facade;
use Lkrms\Console\Console;
use Lkrms\Exception\InvalidCliArgumentException;
use Lkrms\Facade\Composer;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\Reflect;
use Lkrms\Facade\Test;
use Lkrms\Support\PhpDocParser;
use Lkrms\Support\TokenExtractor;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Generates static interfaces for underlying singletons
 *
 */
class GenerateFacadeClass extends GenerateCommand
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

        // These are displaced by Facade if the underlying class has them
        "getInstance",
        "isLoaded",
        "load",
        "unload",
        "unloadAll",
    ];

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

        $this->OutputClass     = $facade;
        $this->OutputNamespace = $facadeNamespace;
        $this->ClassPrefix     = $classPrefix;

        $extends = $this->getFqcnAlias(ltrim(Facade::class, "\\"), "Facade");
        $service = $this->getFqcnAlias($fqcn, $class);

        $package  = $this->getOptionValue("package");
        $desc     = $this->getOptionValue("desc");
        $desc     = is_null($desc) ? "A facade for $service" : $desc;
        $declared = $this->getOptionValue("declared");

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

        $useMap  = [];
        $typeMap = [];
        foreach ($files as $file)
        {
            $useMap[$file]  = (new TokenExtractor($file))->getUseMap();
            $typeMap[$file] = array_change_key_case(array_flip($useMap[$file]), CASE_LOWER);
        }

        $typeNameCallback = function (string $name, bool $returnFqcn = false) use ($typeMap, &$methodFile): ?string
        {
            $name  = trim($name, "\\");
            $alias = $typeMap[$methodFile][strtolower($name)] ?? null;
            return ($alias ? $this->getFqcnAlias($name, $alias, $returnFqcn) : null)
                ?: (!Test::isPhpReservedWord($name) ? $this->getFqcnAlias($name, null, $returnFqcn) : null);
        };
        $phpDocTypeCallback = function (string $type) use (&$methodFile, &$methodNamespace, $useMap, $typeNameCallback): string
        {
            return preg_replace_callback(
                '/(?<!\$)\b' . PhpDocParser::TYPE_PATTERN . '\b/',
                function ($match) use (&$methodFile, &$methodNamespace, $useMap, $typeNameCallback)
                {
                    if (preg_match('/^\\\\/', $match[0]) ||
                        Test::isPhpReservedWord($match[0]))
                    {
                        return $match[0];
                    }
                    return $typeNameCallback(
                        $useMap[$methodFile][$match[0]]
                        ?? "\\" . $methodNamespace . "\\" . $match[0],
                        true
                    );
                },
                $type
            );
        };

        usort($_methods,
            fn(ReflectionMethod $a, ReflectionMethod $b) => $a->isConstructor()
            ? -1 : ($b->isConstructor()
                ? 1 : $a->getName() <=> $b->getName()));
        $facadeMethods = [
            " * @method static $service load() Load and return an instance of the underlying `$class` class",
            " * @method static $service getInstance() Return the underlying `$class` instance",
            " * @method static bool isLoaded() Return true if an underlying `$class` instance has been loaded",
            " * @method static void unload() Clear the underlying `$class` instance",
        ];
        $methods = [];
        foreach ($_methods as $_method)
        {
            $phpDoc          = PhpDocParser::fromDocBlocks(Reflect::getAllMethodDocComments($_method));
            $methodFile      = $_method->getFileName();
            $methodNamespace = $_method->getDeclaringClass()->getNamespaceName();

            if ($_method->isConstructor())
            {
                $method  = "load";
                $type    = $service;
                $summary = "Load and return an instance of the underlying `$class` class";
                unset($facadeMethods[0]);
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

                $type = (($_type = $phpDoc->Return["type"] ?? null)
                    ? $phpDocTypeCallback($_type)
                    : ($_method->hasReturnType()
                        ? Reflect::getTypeDeclaration($_method->getReturnType(), $classPrefix, $typeNameCallback)
                        : "mixed"));
                switch ($type)
                {
                    case 'static':
                    case '$this':
                        $type = $service;
                        break;
                    case 'self':
                        $type = $typeNameCallback($_method->getDeclaringClass()->getName());
                        break;
                }
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

            if (!$methods && !$_method->isConstructor())
            {
                array_push($methods, ...$facadeMethods);
            }

            $methods[] = (" * @method static $type $method("
                . str_replace("\n", "\n * ", implode(", ", $params)) . ")"
                . ($_method->isConstructor()
                    ? " $summary"
                    : ($summary
                        ? " $summary (see {@see $service::$method()})"
                        : " See {@see $service::$method()}")));

            if ($_method->isConstructor())
            {
                array_push($methods, ...$facadeMethods);
            }
        }
        $methods = implode(PHP_EOL, $methods);

        $imports = [];
        foreach ($this->ImportMap as $alias)
        {
            $import = $this->AliasMap[strtolower($alias)];
            if (!strcasecmp($alias, Convert::classToBasename($import)))
            {
                $imports[] = "use $import;";
                continue;
            }
            $imports[] = "use $import as $alias;";
        }
        sort($imports);

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
