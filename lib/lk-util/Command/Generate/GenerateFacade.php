<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Concept\Facade;
use Lkrms\Facade\Env;
use Lkrms\Facade\Reflect;
use Lkrms\Facade\Test;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\LkUtil\Dictionary\EnvVar;
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

    public function getShortDescription(): string
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
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value))
                ->required(),
            CliOption::build()
                ->long('generate')
                ->valueName('facade')
                ->description('The class to generate')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value))
                ->required(),
            ...$this->getOutputOptionList('facade'),
            CliOption::build()
                ->long('declared')
                ->short('e')
                ->description('Ignore inherited methods'),
        ];
    }

    protected function run(string ...$args)
    {
        $namespace = explode('\\', ltrim($this->getOptionValue('class'), '\\'));
        $class = array_pop($namespace);
        $namespace = implode('\\', $namespace) ?: Env::get(EnvVar::NS_DEFAULT, '');
        $fqcn = $namespace ? $namespace . '\\' . $class : $class;

        $facadeNamespace = explode('\\', ltrim($this->getOptionValue('generate'), '\\'));
        $facadeClass = array_pop($facadeNamespace);
        $facadeNamespace = implode('\\', $facadeNamespace) ?: Env::get(EnvVar::NS_FACADE, $namespace);
        $facadeFqcn = $facadeNamespace ? $facadeNamespace . '\\' . $facadeClass : $facadeClass;

        $this->OutputClass = $facadeClass;
        $this->OutputNamespace = $facadeNamespace;
        $classPrefix = $this->getClassPrefix();

        $extends = $this->getFqcnAlias(Facade::class, 'Facade');
        $service = $this->getFqcnAlias($fqcn, $class);

        $desc = $this->OutputDescription;
        $desc = is_null($desc) ? "A facade for $classPrefix$fqcn" : $desc;
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

        $files = [];
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

        $useMap = [];
        $typeMap = [];
        foreach ($files as $file) {
            $useMap[$file] = (new TokenExtractor($file))->getUseMap();
            $typeMap[$file] = array_change_key_case(array_flip($useMap[$file]), CASE_LOWER);
        }

        $typeNameCallback = function (string $name, bool $returnFqcn = false) use ($typeMap, &$methodFile): ?string {
            $alias = $typeMap[$methodFile][ltrim(strtolower($name), '\\')] ?? null;

            return ($alias ? $this->getFqcnAlias($name, $alias, $returnFqcn) : null)
                ?: (Test::isPhpReservedWord($name)
                    ? ($returnFqcn ? $name : null)
                    : $this->getFqcnAlias($name, null, $returnFqcn));
        };
        $phpDocTypeCallback = function (string $type, array $templates) use (&$methodFile, &$methodNamespace, $useMap, $typeNameCallback): string {
            $type = $this->resolveTemplates($type, $templates);

            return preg_replace_callback(
                '/(?<!\$)([a-z]+(-[a-z]+)+|(?=\\\\?\b)' . PhpDocParser::TYPE_PATTERN . ')\b/',
                function ($match) use ($templates, &$methodFile, &$methodNamespace, $useMap, $typeNameCallback) {
                    $type = $this->resolveTemplates($match[0], $templates);

                    // Use reserved words and hyphenated types (e.g. `class-string`) as-is
                    if (Test::isPhpReservedWord($type) || strpbrk($type, '-') !== false) {
                        return $type;
                    }

                    if (preg_match('/^\\\\/', $type)) {
                        return $typeNameCallback($type, true);
                    }

                    return $typeNameCallback(
                        $useMap[$methodFile][$type]
                            ?? '\\' . $methodNamespace . '\\' . $type,
                        true
                    );
                },
                $type
            );
        };

        usort(
            $_methods,
            fn(ReflectionMethod $a, ReflectionMethod $b) =>
                $a->isConstructor()
                    ? -1
                    : ($b->isConstructor()
                        ? 1
                        : $a->getName() <=> $b->getName())
        );
        $facadeMethods = [
            " * @method static $service load() Load and return an instance of the underlying $class class",
            " * @method static $service getInstance() Get the underlying $class instance",
            " * @method static bool isLoaded() True if an underlying $class instance has been loaded",
            " * @method static void unload() Clear the underlying $class instance",
        ];
        $methods = [];
        $toDeclare = [];
        foreach ($_methods as $_method) {
            $docBlocks = Reflect::getAllMethodDocComments($_method, $classDocBlocks);
            $phpDoc = PhpDocParser::fromDocBlocks($docBlocks, $classDocBlocks);
            $methodFile = $_method->getFileName();
            $methodNamespace = $_method->getDeclaringClass()->getNamespaceName();
            $declaring = $typeNameCallback($_method->getDeclaringClass()->getName(), true);
            $methodName = $_method->getName();
            $methodFqsen = "{$declaring}::{$methodName}()";
            $_params = $_method->getParameters();

            // Variables can't be passed to __callStatic by reference, so if
            // this method has any parameters that are passed by reference, it
            // needs a declared facade
            $declare = (bool) array_filter($_params, fn(ReflectionParameter $p) => $p->isPassedByReference());
            $internal = (bool) ($phpDoc->Tags['internal'] ?? null);
            $link = !$internal && $phpDoc && $phpDoc->hasDetail();
            $returnsVoid = false;

            if ($_method->isConstructor()) {
                $method = 'load';
                $type = $service;
                $summary = "Load and return an instance of the underlying $class class";
                unset($facadeMethods[0]);
            } else {
                if ($phpDoc->Tags['deprecated'] ?? null) {
                    continue;
                }
                $method = $methodName;
                if (strpos($method, '__') === 0 ||
                        in_array($method, self::SKIP_METHODS) ||
                        ($declared && $_method->getDeclaringClass() != $_class)) {
                    continue;
                }

                $_type = $phpDoc->Return['type'] ?? null;
                if ($_type && strpbrk($_type, '&<>') === false) {
                    $type = $phpDocTypeCallback($_type, $phpDoc->Templates);
                } else {
                    $type = $_method->hasReturnType()
                        ? Reflect::getTypeDeclaration($_method->getReturnType(), $classPrefix, $typeNameCallback)
                        : 'mixed';

                    // If the underlying method has more type information,
                    // provide a link to it
                    if ($_type) {
                        $link = !$internal;
                    }
                }

                switch ($type) {
                    case 'static':
                    case '$this':
                        $type = $service;
                        break;
                    case 'self':
                        $type = $declaring;
                        break;
                    case 'void':
                        $returnsVoid = true;
                        break;
                }
                $summary = $phpDoc->Summary ?? null;
                $summary = $summary
                    ? ($declare || !$link ? $summary : "$summary (see {@see $methodFqsen})")
                    : ($declare || !$link ? "A facade for $methodFqsen" : "See {@see $methodFqsen}");

                // Work around phpDocumentor's inability to parse "?<type>"
                // return types
                if (!$declare && strpos($type, '?') === 0) {
                    $type = substr($type, 1) . '|null';
                }
            }

            $params = [];
            foreach ($_params as $_param) {
                // Override the declared type if defined in the PHPDoc
                $_type = ($_type = $phpDoc->Params[$_param->getName()]['type'] ?? null) &&
                    strpbrk($_type, '&<>') === false
                        ? $phpDocTypeCallback($_type, $phpDoc->Templates)
                        : null;
                $params[] = $declare
                    ? Reflect::getParameterPhpDoc($_param, $classPrefix, $typeNameCallback, $_type)
                    : Reflect::getParameterDeclaration($_param, $classPrefix, $typeNameCallback, $_type, null, true);
            }

            if (!$methods && !$_method->isConstructor()) {
                array_push($methods, ...$facadeMethods);
            }

            if ($declare) {
                $params = implode(PHP_EOL . ' * ', array_map(
                    fn(string $p) => str_replace(PHP_EOL, PHP_EOL . ' * ', $this->cleanPhpDocTag($p)),
                    array_filter($params)
                ));
                $return = ($type && (!$_method->hasReturnType() ||
                        Reflect::getTypeDeclaration(
                            $_method->getReturnType(),
                            $classPrefix,
                            $typeNameCallback
                        ) !== $type))
                    ? $this->cleanPhpDocTag("@return $type")
                    : '';

                $lines = [];
                $lines[] = '/**';  // 0
                $lines[] = " * $summary";  // 1
                $lines[] = ' *';  // 2
                $lines[] = ' * @internal';  // 3
                $lines[] = " * $params";  // 4
                $lines[] = " * $return";  // 5
                $lines[] = " * @see $methodFqsen";  // 6
                $lines[] = ' */';
                if (!$link) {
                    unset($lines[6]);
                }
                if (!$return) {
                    unset($lines[5]);
                }
                if (!$params) {
                    unset($lines[4]);
                }
                if (!$internal) {
                    unset($lines[3]);
                }

                $toDeclare[] = [$_method, implode(PHP_EOL, $lines), !$returnsVoid];
            } else {
                $methods[] = " * @method static $type $method("
                    . str_replace(PHP_EOL, PHP_EOL . ' * ', implode(', ', $params)) . ')'
                    . " $summary";
            }

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
        $docBlock[] = " * @uses $service";
        $docBlock[] = ' *';
        $docBlock[] = " * @extends $extends<$service>";
        if (!$this->NoMetaTags) {
            $docBlock[] = ' *';
            $docBlock[] = ' * @lkrms-generate-command '
                . implode(
                    ' ',
                    $this->getEffectiveCommandLine(true, [
                        'stdout' => null,
                        'force' => null,
                    ])
                );
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

        array_push(
            $lines,
            ...$this->getStaticGetter('getServiceName', "$service::class")
        );

        /** @var ReflectionMethod $_method */
        foreach ($toDeclare as [$_method, $docBlock, $return]) {
            $_params = $_method->getParameters();
            $return = $return ? 'return ' : '';
            $code = [
                'static::setFuncNumArgs(__FUNCTION__, func_num_args());',
                'try {',
                "    {$return}static::getInstance()->{$_method->name}("
                    . implode(', ', array_map(
                        fn(ReflectionParameter $p) =>
                            ($p->isVariadic() ? '...' : '') . "\${$p->name}",
                        $_params
                    )) . ');',
                '} finally {',
                '    static::clearFuncNumArgs(__FUNCTION__);',
                '}',
            ];

            array_push(
                $lines,
                '',
                ...$this->getMethod($_method->name, $code, $_params, $_method->getReturnType(), $docBlock)
            );
        }

        $lines[] = '}';

        $this->handleOutput($facadeClass, $facadeNamespace, $lines);
    }
}
