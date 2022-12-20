<?php declare(strict_types=1);

/**
 * @package Lkrms\LkUtil
 */

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Concept\Facade;
use Lkrms\Facade\Env;
use Lkrms\Facade\Reflect;
use Lkrms\Facade\Test;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\Support\PhpDocParser;
use Lkrms\Support\TokenExtractor;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Generates static interfaces to underlying classes
 *
 */
final class GenerateFacade extends GenerateCommand
{
    private const SKIP_METHODS = [
        'getReadable',
        'getWritable',
        'setFacade',

        // These are displaced by Facade if the underlying class has them
        'isLoaded',
        'load',
        'unload',
        'unloadAll',
        'getInstance',
    ];

    public function getDescription(): string
    {
        return 'Generate a static interface to a class';
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->long('class')
                ->valueName('class')
                ->description('The class to generate a facade for')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value)),
            CliOption::build()
                ->long('generate')
                ->valueName('facade_class')
                ->description('The class to generate')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value)),
            CliOption::build()
                ->long('package')
                ->short('p')
                ->valueName('PACKAGE')
                ->description('The PHPDoc package')
                ->optionType(CliOptionType::VALUE)
                ->envVariable('PHPDOC_PACKAGE'),
            CliOption::build()
                ->long('desc')
                ->short('d')
                ->valueName('DESCRIPTION')
                ->description('A short description of the facade')
                ->optionType(CliOptionType::VALUE),
            CliOption::build()
                ->long('stdout')
                ->short('s')
                ->description('Write to standard output'),
            CliOption::build()
                ->long('force')
                ->short('f')
                ->description('Overwrite the class file if it already exists'),
            CliOption::build()
                ->long('no-meta')
                ->short('m')
                ->description("Suppress '@lkrms-*' metadata tags"),
            CliOption::build()
                ->long('declared')
                ->short('e')
                ->description('Ignore inherited methods'),
        ];
    }

    protected function run(string ...$args)
    {
        $namespace = explode('\\', ltrim($this->getOptionValue('class'), '\\'));
        $class     = array_pop($namespace);
        $namespace = implode('\\', $namespace) ?: Env::get('DEFAULT_NAMESPACE', '');
        $fqcn      = $namespace ? $namespace . '\\' . $class : $class;

        $facadeNamespace = explode('\\', ltrim($this->getOptionValue('generate'), '\\'));
        $facadeClass     = array_pop($facadeNamespace);
        $facadeNamespace = implode('\\', $facadeNamespace) ?: Env::get('FACADE_NAMESPACE', $namespace);
        $facadeFqcn      = $facadeNamespace ? $facadeNamespace . '\\' . $facadeClass : $facadeClass;
        $classPrefix     = $facadeNamespace ? '\\' : '';

        $this->OutputClass     = $facadeClass;
        $this->OutputNamespace = $facadeNamespace;
        $this->ClassPrefix     = $classPrefix;

        $extends = $this->getFqcnAlias(Facade::class, 'Facade');
        $service = $this->getFqcnAlias($fqcn, $class);

        $package  = $this->getOptionValue('package');
        $desc     = $this->getOptionValue('desc');
        $desc     = is_null($desc) ? "A facade for $classPrefix$fqcn" : $desc;
        $declared = $this->getOptionValue('declared');

        if (!$fqcn) {
            throw new CliArgumentsInvalidException("invalid class: $fqcn");
        }

        if (!$facadeFqcn) {
            throw new CliArgumentsInvalidException("invalid facade: $facadeFqcn");
        }

        try {
            $_class = new ReflectionClass($fqcn);

            if (!$_class->isInstantiable()) {
                throw new CliArgumentsInvalidException("not an instantiable class: $fqcn");
            }
        } catch (ReflectionException $ex) {
            throw new CliArgumentsInvalidException("class does not exist: $fqcn");
        }

        $files        = [];
        $maybeAddFile =
            function ($file) use (&$files) {
                if ($file !== false) {
                    $files[$file] = $file;
                }
            };

        $maybeAddFile($_class->getFileName());
        foreach (($_methods = $_class->getMethods(ReflectionMethod::IS_PUBLIC)) as $_method) {
            $maybeAddFile($_method->getFileName());
        }

        $useMap  = [];
        $typeMap = [];
        foreach ($files as $file) {
            $useMap[$file]  = (new TokenExtractor($file))->getUseMap();
            $typeMap[$file] = array_change_key_case(array_flip($useMap[$file]), CASE_LOWER);
        }

        $typeNameCallback = function (string $name, bool $returnFqcn = false) use ($typeMap, &$methodFile): ?string {
            $alias = $typeMap[$methodFile][ltrim(strtolower($name), '\\')] ?? null;

            return ($alias ? $this->getFqcnAlias($name, $alias, $returnFqcn) : null)
                ?: (Test::isPhpReservedWord($name)
                    ? ($returnFqcn ? $name : null)
                    : $this->getFqcnAlias($name, null, $returnFqcn));
        };
        $phpDocTypeCallback = function (string $type) use (&$methodFile, &$methodNamespace, $useMap, $typeNameCallback): string {
            return preg_replace_callback(
                '/(?<!\$)\b' . PhpDocParser::TYPE_PATTERN . '\b/',
                function ($match) use (&$methodFile, &$methodNamespace, $useMap, $typeNameCallback) {
                    if (preg_match('/^\\\\/', $match[0]) ||
                            Test::isPhpReservedWord($match[0])) {
                        return $match[0];
                    }

                    return $typeNameCallback(
                        $useMap[$methodFile][$match[0]]
                            ?? '\\' . $methodNamespace . '\\' . $match[0],
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
            " * @method static $service load() Load and return an instance of the underlying $class class",
            " * @method static $service getInstance() Return the underlying $class instance",
            " * @method static bool isLoaded() Return true if an underlying $class instance has been loaded",
            " * @method static void unload() Clear the underlying $class instance",
        ];
        $methods          = [];
        $methodsToDeclare = [];
        foreach ($_methods as $_method) {
            $phpDoc          = PhpDocParser::fromDocBlocks(Reflect::getAllMethodDocComments($_method));
            $methodFile      = $_method->getFileName();
            $methodNamespace = $_method->getDeclaringClass()->getNamespaceName();
            $_params         = $_method->getParameters();

            if ($_method->isConstructor()) {
                $method  = 'load';
                $type    = $service;
                $summary = "Load and return an instance of the underlying $class class";
                unset($facadeMethods[0]);
            } else {
                if ($phpDoc->Tags['deprecated'] ?? null) {
                    continue;
                }
                $method = $_method->getName();
                if (strpos($method, '__') === 0 ||
                        in_array($method, self::SKIP_METHODS) ||
                        ($declared && $_method->getDeclaringClass() != $_class)) {
                    continue;
                }

                // If any parameters are passed by reference, __callStatic won't
                // work and the method will need its own facade
                if (array_filter($_params, fn(ReflectionParameter $p) => $p->isPassedByReference())) {
                    $methodsToDeclare[] = $_method;
                    //continue;
                }

                $type = ($_type = $phpDoc->Return['type'] ?? null) && strpbrk($_type, '<>') === false
                    ? $phpDocTypeCallback($_type)
                    : ($_method->hasReturnType()
                        ? Reflect::getTypeDeclaration($_method->getReturnType(), $classPrefix, $typeNameCallback)
                        : 'mixed');
                switch ($type) {
                    case 'static':
                    case '$this':
                        $type = $service;
                        break;
                    case 'self':
                        $type = $typeNameCallback($_method->getDeclaringClass()->getName(), true);
                        break;
                }
                $summary = $phpDoc->Summary ?? null;

                // Work around phpDocumentor's inability to parse "?<type>"
                // return types
                if (strpos($type, '?') === 0) {
                    $type = substr($type, 1) . '|null';
                }
            }

            $params = [];
            foreach ($_params as $_param) {
                $params[] = Reflect::getParameterDeclaration(
                    $_param,
                    $classPrefix,
                    $typeNameCallback,
                    // Override the declared type if defined in the PHPDoc
                    (($_type = $phpDoc->Params[$_param->getName()]['type'] ?? null) && strpbrk($_type, '<>') === false
                        ? $phpDocTypeCallback($_type)
                        : null)
                );
            }

            if (!$methods && !$_method->isConstructor()) {
                array_push($methods, ...$facadeMethods);
            }

            $methods[] = " * @method static $type $method("
                . str_replace("\n", "\n * ", implode(', ', $params)) . ')'
                . ($_method->isConstructor()
                    ? " $summary"
                    : ($summary
                        ? " $summary (see {@see " . $typeNameCallback($_method->getDeclaringClass()->getName(), true) . "::$method()})"
                        : ' See {@see ' . $typeNameCallback($_method->getDeclaringClass()->getName(), true) . "::$method()}"));

            if ($_method->isConstructor()) {
                array_push($methods, ...$facadeMethods);
            }
        }
        $methods = implode(PHP_EOL, $methods);

        $imports = $this->getImports();

        $docBlock[] = '/**';
        if ($desc) {
            $docBlock[] = " * $desc";
            $docBlock[] = ' *';
        }
        if ($methods) {
            $docBlock[] = $methods;
            $docBlock[] = ' *';
        }
        if ($package) {
            $docBlock[] = " * @package $package";
        }
        $docBlock[] = " * @uses $service";
        if (!$this->getOptionValue('no-meta')) {
            $docBlock[] = ' * @lkrms-generate-command '
                . implode(' ', $this->getEffectiveCommandLine(true, [
                    'stdout' => null,
                    'force'  => null,
                ]));
        }
        $docBlock[] = ' */';

        $blocks = [
            '<?php declare(strict_types=1);',
            "namespace $facadeNamespace;",
            implode(PHP_EOL, $imports),
            implode(PHP_EOL, $docBlock) . PHP_EOL
                . "final class $facadeClass extends $extends" . PHP_EOL
                . '{'
        ];

        if (!$imports) {
            unset($blocks[3]);
        }

        if (!$facadeNamespace) {
            unset($blocks[2]);
        }

        $lines = [implode(PHP_EOL . PHP_EOL, $blocks)];

        array_push($lines,
                   ...$this->getStaticGetter('getServiceName', "$service::class"));

        /** @var ReflectionMethod $_method */
        foreach ($methodsToDeclare as $_method) {
            $_params = $_method->getParameters();
            $code    = [
                "return static::getInstance()->{$_method->name}("
                    . implode(', ', array_map(
                        fn(ReflectionParameter $p) => "\${$p->name}",
                        $_params
                    )) . ');',
            ];

            array_push($lines, '',
                       ...$this->getMethod($_method->name, $code, $_params, $_method->getReturnType()));
        }

        $lines[] = '}';

        $this->handleOutput($facadeClass, $facadeNamespace, $lines);
    }
}
