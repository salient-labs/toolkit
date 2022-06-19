<?php

declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOptionType;
use Lkrms\Concept\Facade;
use Lkrms\Console\Console;
use Lkrms\Exception\InvalidCliArgumentException;
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
        ];
    }

    protected function _run(string ...$args)
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

        $package = $this->getOptionValue("package");
        $desc    = $this->getOptionValue("desc") ?: "A facade for $class";

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

        $useMap = [];
        foreach ($files as $file)
        {
            $useMap = array_merge((new TokenExtractor($file))->getUseMap(), $useMap);
        }
        $useMap  = array_merge(["Facade" => ltrim(Facade::class, "\\"), $class => $fqcn], $useMap);
        $typeMap = array_change_key_case(array_flip($useMap), CASE_LOWER);
        $useMap  = [];

        $typeNameCallback = function (string $name) use ($facadeNamespace, $typeMap, &$useMap): ?string
        {
            $name = trim($name, "\\");
            $ns   = Convert::classToNamespace($name);
            if (!strcasecmp($ns, $facadeNamespace))
            {
                return Convert::classToBasename($name);
            }
            if ($alias = $typeMap[strtolower($name)] ?? null)
            {
                $useMap[$alias] = $name;
                return $alias;
            }
            return null;
        };
        $typeCallback = function (string $name) use ($typeNameCallback): string
        {
            return $typeNameCallback($name) ?: $name;
        };

        $methods = [];
        foreach ($_methods as $_method)
        {
            $method = $_method->getName();
            if (strpos($method, "__") === 0)
            {
                continue;
            }

            $params = [];
            foreach ($_method->getParameters() as $_param)
            {
                $params[] = Reflect::getParameterDeclaration($_param, $classPrefix, $typeNameCallback);
            }

            $methods[] = " * @method static "
                . ($_method->hasReturnType() ? Reflect::getTypeDeclaration($_method->getReturnType(), $classPrefix, $typeNameCallback) . " " : "mixed ")
                . $_method->getName()
                . "(" . str_replace("\n", "\n * ", implode(", ", $params)) . ")";
        }
        $methods = implode(PHP_EOL, $methods);

        $extends = $typeCallback("$classPrefix$extends");
        $service = $typeCallback("$classPrefix$service");

        $imports = [];
        foreach ($useMap as $from => $to)
        {
            if (!strcasecmp($from, Convert::classToBasename($to)))
            {
                $imports[] = "use $to;";
                continue;
            }
            $imports[] = "use $to as $from;";
        }
        sort($imports);

        $docBlock = [
            "/**",
            " * $desc",
            " *",
            " * @uses $service",
            " *",
            $methods,
            " *",
            " * @package $package",
            " */",
        ];

        if (!$package)
        {
            unset($docBlock[7]);
        }

        if (!$methods)
        {
            unset($docBlock[6], $docBlock[5]);
        }

        $blocks = [
            "<?php",
            "declare(strict_types=1);",
            "namespace $facadeNamespace;",
            implode(PHP_EOL, $imports),
            implode(PHP_EOL, $docBlock) . PHP_EOL .
            "class $facade extends $extends" . PHP_EOL .
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
