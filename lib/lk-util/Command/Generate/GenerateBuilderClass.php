<?php

declare(strict_types=1);

/**
 * @package Lkrms\LkUtil
 */

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\CliOptionType;
use Lkrms\Concept\Builder;
use Lkrms\Console\Console;
use Lkrms\Contract\IContainer;
use Lkrms\Exception\InvalidCliArgumentException;
use Lkrms\Facade\Composer;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\Reflect;
use Lkrms\Facade\Test;
use Lkrms\Support\ClosureBuilder;
use Lkrms\Support\PhpDocParser;
use Lkrms\Support\TokenExtractor;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Generates fluent interfaces that create instances of a class
 *
 */
class GenerateBuilderClass extends GenerateCommand
{
    protected function _getDescription(): string
    {
        return "Generate a fluent interface that creates instances of a class";
    }

    protected function _getOptions(): array
    {
        return [
            [
                "long"        => "class",
                "short"       => "c",
                "valueName"   => "CLASS",
                "description" => "The class to generate a builder for",
                "optionType"  => CliOptionType::VALUE,
                "required"    => true,
            ],
            [
                "long"        => "generate",
                "short"       => "g",
                "valueName"   => "CLASS",
                "description" => "The class to generate",
                "optionType"  => CliOptionType::VALUE,
            ],
            [
                "long"         => "static-builder",
                "short"        => "b",
                "valueName"    => "METHOD",
                "description"  => "The static method that returns a new builder",
                "optionType"   => CliOptionType::VALUE,
                "defaultValue" => "build",
            ],
            [
                "long"         => "terminator",
                "short"        => "t",
                "valueName"    => "METHOD",
                "description"  => "The method that terminates the interface",
                "optionType"   => CliOptionType::VALUE,
                "defaultValue" => "go",
            ],
            [
                "long"        => "extend",
                "short"       => "x",
                "valueName"   => "CLASS",
                "description" => "The Builder subclass to extend",
                "optionType"  => CliOptionType::VALUE,
            ],
            [
                "long"        => "no-final",
                "short"       => "a",
                "description" => "Do not declare the generated class 'final'",
            ],
            [
                "long"        => "package",
                "short"       => "p",
                "valueName"   => "PACKAGE",
                "description" => "The PHPDoc package",
                "optionType"  => CliOptionType::VALUE,
                "envVariable" => "PHPDOC_PACKAGE",
            ],
            [
                "long"        => "desc",
                "short"       => "d",
                "valueName"   => "DESCRIPTION",
                "description" => "A short description of the builder",
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
                "description" => "Ignore inherited properties",
            ],
        ];
    }

    public $SkipProperties = [];

    protected function run(string ...$args)
    {
        $namespace = explode("\\", ltrim($this->getOptionValue("class"), "\\"));
        $class     = array_pop($namespace);
        $namespace = implode("\\", $namespace) ?: Env::get("DEFAULT_NAMESPACE", "");
        $fqcn      = $namespace ? $namespace . "\\" . $class : $class;

        $builderNamespace = explode("\\", ltrim($this->getOptionValue("generate") ?: $fqcn . "Builder", "\\"));
        $builderClass     = array_pop($builderNamespace);
        $builderNamespace = implode("\\", $builderNamespace) ?: Env::get("BUILDER_NAMESPACE", $namespace);
        $builderFqcn      = $builderNamespace ? $builderNamespace . "\\" . $builderClass : $builderClass;
        $classPrefix      = $builderNamespace ? "\\" : "";

        $extendsNamespace = explode("\\", ltrim($this->getOptionValue("extend") ?: Builder::class, "\\"));
        $extendsClass     = array_pop($extendsNamespace);
        $extendsNamespace = implode("\\", $extendsNamespace) ?: Env::get("BUILDER_NAMESPACE", $namespace);
        $extendsFqcn      = $extendsNamespace ? $extendsNamespace . "\\" . $extendsClass : $extendsClass;

        $this->OutputClass     = $builderClass;
        $this->OutputNamespace = $builderNamespace;
        $this->ClassPrefix     = $classPrefix;

        $extends   = $this->getFqcnAlias($extendsFqcn, $extendsClass);
        $service   = $this->getFqcnAlias($fqcn, $class);
        $container = $this->getFqcnAlias(IContainer::class);

        $staticBuilder = Convert::toCamelCase($this->getOptionValue("static-builder"));
        $terminator    = Convert::toCamelCase($this->getOptionValue("terminator"));
        array_push($this->SkipProperties, $staticBuilder, $terminator);

        $package  = $this->getOptionValue("package");
        $desc     = $this->getOptionValue("desc");
        $desc     = is_null($desc) ? "A fluent interface for creating $class objects" : $desc;
        $declared = $this->getOptionValue("declared");

        if (!$fqcn)
        {
            throw new InvalidCliArgumentException("invalid class: $fqcn");
        }

        if (!$builderFqcn)
        {
            throw new InvalidCliArgumentException("invalid builder: $builderFqcn");
        }

        if (!$extendsFqcn)
        {
            throw new InvalidCliArgumentException("invalid builder subclass: $extendsFqcn");
        }

        if (!is_a($extendsFqcn, Builder::class, true))
        {
            throw new InvalidCliArgumentException("not a subclass of Builder: $extendsClass");
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

        $writable = ClosureBuilder::get($_class->getName())->getWritableProperties();
        $writable = array_combine(
            array_map(
                fn(string $name) => Convert::toCamelCase($name),
                $writable
            ),
            $writable
        );

        /** @var ReflectionParameter[] */
        $_params          = [];
        if ($_constructor = $_class->getConstructor())
        {
            foreach ($_constructor->getParameters() as $_param)
            {
                $name = Convert::toCamelCase($_param->getName());
                unset($writable[$name]);
                $_params[$name] = $_param;
            }
        }

        /** @var ReflectionProperty[] */
        $_properties = [];
        $maybeAddFile($_class->getFileName());
        foreach ($writable as $name => $property)
        {
            $_properties[$name] = $_property = $_class->getProperty($property);
            $maybeAddFile($_property->getDeclaringClass()->getFileName());
        }

        $useMap  = [];
        $typeMap = [];
        foreach ($files as $file)
        {
            $useMap[$file]  = (new TokenExtractor($file))->getUseMap();
            $typeMap[$file] = array_change_key_case(array_flip($useMap[$file]), CASE_LOWER);
        }

        $typeNameCallback = function (string $name, bool $returnFqcn = false) use ($typeMap, &$propertyFile): ?string
        {
            $alias = $typeMap[$propertyFile][ltrim(strtolower($name), "\\")] ?? null;
            return ($alias ? $this->getFqcnAlias($name, $alias, $returnFqcn) : null)
                ?: (Test::isPhpReservedWord($name)
                    ? ($returnFqcn ? $name : null)
                    : $this->getFqcnAlias($name, null, $returnFqcn));
        };
        $phpDocTypeCallback = function (string $type) use (&$propertyFile, &$propertyNamespace, $useMap, $typeNameCallback): string
        {
            return preg_replace_callback(
                '/(?<!\$)\b' . PhpDocParser::TYPE_PATTERN . '\b/',
                function ($match) use (&$propertyFile, &$propertyNamespace, $useMap, $typeNameCallback)
                {
                    if (preg_match('/^\\\\/', $match[0]) ||
                        Test::isPhpReservedWord($match[0]))
                    {
                        return $match[0];
                    }
                    return $typeNameCallback(
                        $useMap[$propertyFile][$match[0]]
                        ?? "\\" . $propertyNamespace . "\\" . $match[0],
                        true
                    );
                },
                $type
            );
        };

        $names = array_keys($_params + $_properties);
        //sort($names);
        $methods = [
            " * @method static \$this $staticBuilder(?$container \$container = null) Create a new $builderClass (syntactic sugar for 'new $builderClass()')",
        ];
        foreach ($names as $name)
        {
            if (in_array($name, $this->SkipProperties))
            {
                continue;
            }

            if ($_property = $_properties[$name] ?? null)
            {
                if ($declared && $_property->getDeclaringClass() != $_class)
                {
                    continue;
                }

                $phpDoc            = PhpDocParser::fromDocBlocks(Reflect::getAllPropertyDocComments($_property));
                $propertyFile      = $_property->getDeclaringClass()->getFileName();
                $propertyNamespace = $_property->getDeclaringClass()->getNamespaceName();

                $type = (($_type = $phpDoc->Var[0]["type"] ?? null) && strpbrk($_type, "<>") === false
                    ? $phpDocTypeCallback($_type)
                    : ($_property->hasType()
                        ? Reflect::getTypeDeclaration($_property->getType(), $classPrefix, $typeNameCallback)
                        : "mixed"));
                switch ($type)
                {
                    case 'static':
                    case '$this':
                        $type = $service;
                        break;
                    case 'self':
                        $type = $typeNameCallback($_property->getDeclaringClass()->getName(), true);
                        break;
                }
                $summary = $phpDoc->Summary ?? null;

                $methods[] = " * @method \$this $name($type \$value)" .
                    ($summary
                        ? " $summary (see {@see " . $typeNameCallback($_property->getDeclaringClass()->getName(), true) . "::\$" . $_property->getName() . "})"
                        : " See {@see " . $typeNameCallback($_property->getDeclaringClass()->getName(), true) . "::\$" . $_property->getName() . "}");

                continue;
            }

            $phpDoc            = PhpDocParser::fromDocBlocks(Reflect::getAllMethodDocComments($_constructor));
            $propertyFile      = $_constructor->getFileName();
            $propertyNamespace = $_constructor->getDeclaringClass()->getNamespaceName();

            $_param = $_params[$name];
            $_name  = $_param->getName();

            $type = (($_type = $phpDoc->Params[$_name]["type"] ?? null) && strpbrk($_type, "<>") === false
                ? $phpDocTypeCallback($_type)
                : ($_param->hasType()
                    ? Reflect::getTypeDeclaration($_param->getType(), $classPrefix, $typeNameCallback)
                    : "mixed"));
            $default = "";
            switch ($type)
            {
                case 'static':
                case '$this':
                    $type = $service;
                    break;
                case 'self':
                    $type = $typeNameCallback($_constructor->getDeclaringClass()->getName(), true);
                    break;
                case 'bool':
                    $default = " = true";
                    break;
            }
            $summary = $phpDoc->Summary ?? null;

            $methods[] = " * @method \$this $name($type \$value$default)" .
                ($summary ? " $summary" : "");
        }
        $methods[] = " * @method $service $terminator() Return a new $class object";
        $methods   = implode(PHP_EOL, $methods);

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
            "namespace $builderNamespace;",
            implode(PHP_EOL, $imports),
            implode(PHP_EOL, $docBlock) . PHP_EOL .
            ($this->getOptionValue("no-final") ? "" : "final ") .
            "class $builderClass extends $extends" . PHP_EOL .
            "{"
        ];

        if (!$imports)
        {
            unset($blocks[3]);
        }

        if (!$builderNamespace)
        {
            unset($blocks[2]);
        }

        $lines = [implode(PHP_EOL . PHP_EOL, $blocks)];

        array_push($lines,
            ...$this->getStaticGetter("getClassName", "$service::class"));

        if ($this->getoption("static-builder")->DefaultValue !== $staticBuilder)
        {
            array_push($lines, "",
                ...$this->getStaticGetter("getStaticBuilder", var_export($staticBuilder, true)));
        }

        if ($this->getoption("terminator")->DefaultValue !== $terminator)
        {
            array_push($lines, "",
                ...$this->getStaticGetter("getTerminator", var_export($terminator, true)));
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
            $file = "$builderClass.php";

            if ($dir = Composer::getNamespacePath($builderNamespace))
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
