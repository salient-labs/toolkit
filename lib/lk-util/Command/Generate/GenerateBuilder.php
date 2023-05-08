<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Closure;
use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Reflect;
use Lkrms\LkUtil\Catalog\EnvVar;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Support\Introspector;
use Lkrms\Support\PhpDoc\PhpDoc;
use Lkrms\Support\PhpDoc\PhpDocTag;
use Lkrms\Support\TokenExtractor;
use Lkrms\Utility\Test;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Generates fluent interfaces that create instances of a class
 *
 */
class GenerateBuilder extends GenerateCommand
{
    private ?string $ClassFqcn;

    private ?string $BuilderFqcn;

    private ?string $ExtendsFqcn;

    /**
     * @var string[]
     */
    private array $SkipProperties = [];

    public function getShortDescription(): string
    {
        return 'Generate a fluent interface that creates instances of a class';
    }

    protected function getOptionList(): array
    {
        $toCamelCase =
            fn(string $value) =>
                Convert::toCamelCase($value);

        return [
            CliOption::build()
                ->long('class')
                ->valueName('class')
                ->description('The class to generate a builder for')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required()
                ->bindTo($this->ClassFqcn),
            CliOption::build()
                ->long('generate')
                ->short('g')
                ->valueName('class')
                ->description('The class to generate')
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->BuilderFqcn),
            CliOption::build()
                ->long('static-builder')
                ->short('b')
                ->valueName('method')
                ->description('The static method that returns a new builder')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('build')
                ->valueCallback($toCamelCase),
            CliOption::build()
                ->long('value-getter')
                ->short('V')
                ->valueName('method')
                ->description('The method that returns a value if it has been set')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('get')
                ->valueCallback($toCamelCase),
            CliOption::build()
                ->long('value-checker')
                ->short('c')
                ->valueName('method')
                ->description('The method that returns true if a value has been set')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('isset')
                ->valueCallback($toCamelCase),
            CliOption::build()
                ->long('terminator')
                ->short('t')
                ->valueName('method')
                ->description('The method that terminates the interface')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('go')
                ->valueCallback($toCamelCase),
            CliOption::build()
                ->long('static-resolver')
                ->short('r')
                ->valueName('method')
                ->description('The static method that resolves unterminated builders')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('resolve')
                ->valueCallback($toCamelCase),
            CliOption::build()
                ->long('extend')
                ->short('x')
                ->valueName('class')
                ->description('The Builder subclass to extend')
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->ExtendsFqcn),
            CliOption::build()
                ->long('no-final')
                ->short('a')
                ->description("Do not declare the generated class 'final'"),
            ...$this->getOutputOptionList('builder'),
            CliOption::build()
                ->long('declared')
                ->short('e')
                ->description('Ignore inherited properties'),
        ];
    }

    protected function run(string ...$args)
    {
        $classFqcn = $this->getFqcnOptionValue(
            $this->ClassFqcn,
            null,
            $classClass
        );

        $builderFqcn = $this->getFqcnOptionValue(
            $this->BuilderFqcn ?: $classFqcn . 'Builder',
            EnvVar::NS_BUILDER,
            $builderClass,
            $builderNamespace
        );

        $extendsFqcn = $this->getFqcnOptionValue(
            $this->ExtendsFqcn ?: Builder::class,
            EnvVar::NS_BUILDER,
            $extendsClass
        );

        $this->OutputClass = $builderClass;
        $this->OutputNamespace = $builderNamespace;
        $classPrefix = $this->getClassPrefix();

        $extends = $this->getFqcnAlias($extendsFqcn, $extendsClass);
        $service = $this->getFqcnAlias($classFqcn, $classClass);
        $container = $this->getFqcnAlias(IContainer::class);

        array_push(
            $this->SkipProperties,
            $staticBuilder = $this->getOptionValue('static-builder'),
            $valueGetter = $this->getOptionValue('value-getter'),
            $valueChecker = $this->getOptionValue('value-checker'),
            $terminator = $this->getOptionValue('terminator'),
            $staticResolver = $this->getOptionValue('static-resolver')
        );

        $desc = $this->OutputDescription;
        $desc = is_null($desc)
            ? "A fluent interface for creating $classClass objects"
            : $desc;
        $declared = $this->getOptionValue('declared');

        if (!$classFqcn) {
            throw new CliInvalidArgumentsException("invalid class: $classFqcn");
        }

        if (!$builderFqcn) {
            throw new CliInvalidArgumentsException("invalid builder: $builderFqcn");
        }

        if (!$extendsFqcn) {
            throw new CliInvalidArgumentsException("invalid builder subclass: $extendsFqcn");
        }

        if (!is_a($extendsFqcn, Builder::class, true)) {
            throw new CliInvalidArgumentsException("not a subclass of Builder: $extendsFqcn");
        }

        try {
            $_class = new ReflectionClass($classFqcn);
            if (!$_class->isInstantiable() && !$_class->isAbstract()) {
                throw new CliInvalidArgumentsException("not an instantiable class: $classFqcn");
            }
        } catch (ReflectionException $ex) {
            throw new CliInvalidArgumentsException("class does not exist: $classFqcn");
        }

        $_docBlocks = Reflect::getAllClassDocComments($_class);
        $classPhpDoc = PhpDoc::fromDocBlocks($_docBlocks);

        $files = [];
        $maybeAddFile =
            function ($file) use (&$files) {
                if ($file !== false) {
                    $files[$file] = $file;
                }
            };

        $writable = Introspector::get($_class->getName())->getWritableProperties();
        $writable = array_combine(
            array_map(
                fn(string $name) => Convert::toCamelCase($name),
                $writable
            ),
            $writable
        );

        /** @var ReflectionParameter[] */
        $_params = [];
        $toDeclare = [];
        $docBlocks = [];
        if ($_constructor = $_class->getConstructor()) {
            foreach ($_constructor->getParameters() as $_param) {
                $name = Convert::toCamelCase($_param->getName());
                unset($writable[$name]);
                $_params[$name] = $_param;
                // Variables can't be passed to __call by reference, so this
                // parameter needs to be received via a declared method
                if ($_param->isPassedByReference()) {
                    $toDeclare[$name] = $_param;
                }
            }
        }

        /** @var ReflectionProperty[] */
        $_allProperties = [];
        /** @var array<string,mixed> */
        $_defaultProperties = [];
        $defaults = $_class->getDefaultProperties();
        foreach ($_class->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED) as $_property) {
            if ($_property->isStatic()) {
                continue;
            }
            $_name = $_property->getName();
            $name = Convert::toCamelCase($_name);
            $_allProperties[$name] = $_property;
            if (array_key_exists($_name, $defaults)) {
                $_defaultProperties[$name] = $defaults[$_name];
            }
        }

        /** @var ReflectionProperty[] */
        $_properties = [];
        $maybeAddFile($_class->getFileName());
        foreach (array_keys($writable) as $name) {
            $_properties[$name] = $_property = $_allProperties[$name];
            $maybeAddFile($_property->getDeclaringClass()->getFileName());
        }

        $useMap = [];
        $typeMap = [];
        foreach ($files as $file) {
            $useMap[$file] = (new TokenExtractor($file))->getUseMap();
            $typeMap[$file] = array_change_key_case(array_flip($useMap[$file]), CASE_LOWER);
        }

        $typeNameCallback = function (string $name, bool $returnFqcn = false) use ($typeMap, &$propertyFile): ?string {
            $alias = $typeMap[$propertyFile][ltrim(strtolower($name), '\\')] ?? null;

            return ($alias ? $this->getFqcnAlias($name, $alias, $returnFqcn) : null)
                ?: (Test::isPhpReservedWord($name)
                    ? ($returnFqcn ? $name : null)
                    : $this->getFqcnAlias($name, null, $returnFqcn));
        };
        $phpDocTypeCallback = function (string $type, array $templates) use (&$propertyFile, &$propertyNamespace, $useMap, $typeNameCallback): string {
            return PhpDocTag::normaliseType(preg_replace_callback(
                '/(?<!\$)([a-z]+(-[a-z]+)+|(?=\\\\?\b)' . Regex::PHP_TYPE . ')\b/',
                function ($match) use ($templates, &$propertyFile, &$propertyNamespace, $useMap, $typeNameCallback) {
                    $type = $this->resolveTemplates($match[0], $templates);

                    // Use reserved words and hyphenated types (e.g. `class-string`) as-is
                    if (Test::isPhpReservedWord($type) || strpbrk($type, '-') !== false) {
                        return $type;
                    }

                    if (preg_match('/^\\\\/', $type)) {
                        return $typeNameCallback($type, true);
                    }

                    return $typeNameCallback(
                        $useMap[$propertyFile][$type]
                            ?? '\\' . $propertyNamespace . '\\' . $type,
                        true
                    );
                },
                $type
            ));
        };

        $_docBlocks = Reflect::getAllMethodDocComments($_constructor, $classDocBlocks);
        $_phpDoc = PhpDoc::fromDocBlocks($_docBlocks, $classDocBlocks);

        $names = array_keys($_params + $_properties);
        //sort($names);
        $methods = [
            " * @method static \$this $staticBuilder(?$container \$container = null) Create a new $builderClass (syntactic sugar for 'new $builderClass()')",
        ];
        foreach ($names as $name) {
            if (in_array($name, $this->SkipProperties)) {
                continue;
            }

            if ($_property = $_properties[$name] ?? null) {
                if ($declared && $_property->getDeclaringClass() != $_class) {
                    continue;
                }

                $_docBlocks = Reflect::getAllPropertyDocComments($_property, $classDocBlocks);
                $phpDoc = PhpDoc::fromDocBlocks($_docBlocks, $classDocBlocks);
                $propertyFile = $_property->getDeclaringClass()->getFileName();
                $propertyNamespace = $_property->getDeclaringClass()->getNamespaceName();

                $internal = (bool) ($phpDoc->TagsByName['internal'] ?? null);
                $link = !$internal && $phpDoc && $phpDoc->hasDetail();

                $_type = $phpDoc->Vars[0]->Type ?? $phpDoc->Vars[$_property->getName()]->Type ?? null;
                if ($_type /*&& strpbrk($_type, '&<>') === false*/) {
                    $type = $phpDocTypeCallback($_type, $phpDoc->Templates);
                } else {
                    $type = $_property->hasType()
                        ? Reflect::getTypeDeclaration($_property->getType(), $classPrefix, $typeNameCallback)
                        : '';

                    // If the underlying property has more type information,
                    // provide a link to it
                    //
                    // @phpstan-ignore-next-line
                    if ($_type) {
                        $link = !$internal;
                    }
                }

                $default = '';
                $defaultText = null;
                switch (ltrim($type, '?')) {
                    case 'static':
                    case '$this':
                        $type = $service;
                        break;
                    case 'self':
                        $type = $typeNameCallback($_property->getDeclaringClass()->getName(), true);
                        break;
                    case 'bool':
                        $default = ' = true';
                        if (array_key_exists($name, $_defaultProperties)) {
                            $defaultValue = $_defaultProperties[$name];
                            if (!is_null($defaultValue)) {
                                $defaultText = sprintf(
                                    'default: %s',
                                    var_export($defaultValue, true)
                                );
                            }
                        }
                        break;
                }
                $summary = $phpDoc->Summary ?? null;
                if (!$summary && ($_param = $_params[$name] ?? null)) {
                    $summary = $_phpDoc ? $_phpDoc->unwrap($_phpDoc->Params[$_param->getName()]->Description ?? null) : null;
                }

                $type = $type ? "$type " : '';
                $methods[] = " * @method \$this $name($type\$value$default)"
                    . $this->getSummary(
                        $summary,
                        $_property,
                        $typeNameCallback,
                        null,
                        null,
                        $defaultText,
                        false,
                        $link
                    );

                continue;
            }

            $propertyFile = $_constructor->getFileName();
            $propertyNamespace = $_constructor->getDeclaringClass()->getNamespaceName();
            $declaringClass = $typeNameCallback($_constructor->getDeclaringClass()->getName(), true);
            $declare = array_key_exists($name, $toDeclare);

            // If the parameter has a matching property, retrieve its DocBlock
            if ($_property = $_allProperties[$name] ?? null) {
                $_docBlocks = Reflect::getAllPropertyDocComments($_property, $classDocBlocks);
                $phpDoc = PhpDoc::fromDocBlocks($_docBlocks, $classDocBlocks);
            } else {
                $phpDoc = null;
            }
            $internal = (bool) ($phpDoc->TagsByName['internal'] ?? null);
            $link = !$internal && $phpDoc && $phpDoc->hasDetail();

            $_param = $_params[$name];
            $_name = $_param->getName();

            $_type = $_phpDoc->Params[$_name]->Type ?? null;
            if ($_type /*&& strpbrk($_type, '&<>') === false*/) {
                $type = $phpDocTypeCallback($_type, $_phpDoc->Templates);
            } else {
                $type = $_param->hasType()
                    ? Reflect::getTypeDeclaration($_param->getType(), $classPrefix, $typeNameCallback)
                    : '';

                // If the underlying parameter has more type information,
                // provide a link to it
                //
                // @phpstan-ignore-next-line
                if ($_type) {
                    // Ensure the link is to the constructor, not the property,
                    // unless both are annotated with the same type
                    if ($_property &&
                            $phpDoc &&
                            ($_propertyType = $phpDoc->Vars[0]->Type ?? $phpDoc->Vars[$_property->getName()]->Type ?? null) &&
                            // strpbrk($_propertyType, '&<>') === false &&
                            $_propertyType !== $_type) {
                        $_property = null;
                    }
                    $link = true;
                }
            }

            $default = '';
            $defaultText = null;
            switch (ltrim($type, '?')) {
                case 'static':
                case '$this':
                    $type = $service;
                    break;
                case 'self':
                    $type = $declaringClass;
                    break;
                case 'bool':
                    $default = ' = true';
                    if ($_param->isDefaultValueAvailable()) {
                        $defaultValue = $_param->getDefaultValue();
                        if (!is_null($defaultValue)) {
                            $defaultText = sprintf(
                                'default: %s',
                                var_export($defaultValue, true)
                            );
                        }
                    }
                    break;
            }

            $summary = $_phpDoc ? $_phpDoc->unwrap($_phpDoc->Params[$_name]->Description ?? null) : null;
            if (!$summary && $phpDoc) {
                $summary = $phpDoc->Summary;
            }

            if ($declare) {
                $param = Reflect::getParameterPhpDoc(
                    $_param, $classPrefix, $typeNameCallback, $_type, 'variable'
                );

                $lines = [];
                $lines[] = '/**';
                $lines[] = ' * ' . $this->getSummary($summary, $_property, $typeNameCallback, $declaringClass, $name, null, true, $link, $see);
                $lines[] = ' *';
                $lines[] = " * $param";
                $lines[] = ' * @return $this';
                $lines[] = " * @see $see";
                $lines[] = ' */';
                if (!$link) {
                    unset($lines[5]);
                }
                if (!$param) {
                    unset($lines[3]);
                }
                $docBlocks[$name] = implode(PHP_EOL, $lines);
            } else {
                $type = $type ? "$type " : '';
                $methods[] = " * @method \$this $name($type\$value$default)"
                    . $this->getSummary(
                        $summary,
                        $_property,
                        $typeNameCallback,
                        $declaringClass,
                        $name,
                        $defaultText,
                        false,
                        $link
                    );
            }
        }
        $methods[] = " * @method mixed $valueGetter(string \$name) The value of \$name if applied to the unresolved $classClass by calling \$name(), otherwise null";
        $methods[] = " * @method bool $valueChecker(string \$name) True if a value for \$name has been applied to the unresolved $classClass by calling \$name()";
        $methods[] = " * @method $service $terminator() Get a new $classClass object";
        $methods[] = " * @method static $service $staticResolver($service|$builderClass \$object) Resolve a $builderClass or $classClass object to a $classClass object";
        $methods = implode(PHP_EOL, $methods);

        $docBlock[] = '/**';
        if ($desc) {
            $docBlock[] = " * $desc";
            $docBlock[] = ' *';
        }
        $docBlock[] = $methods;
        $docBlock[] = ' *';
        $docBlock[] = " * @uses $service";
        $docBlock[] = ' *';
        $templates = '';
        if ($classPhpDoc->Templates) {
            $templates = [];
            foreach ($classPhpDoc->Templates as $template => $tag) {
                $templateOf = $tag->Type;
                if (!Test::isPhpReservedWord($templateOf) &&
                        !array_key_exists($templateOf, $classPhpDoc->Templates)) {
                    $templateOf = $phpDocTypeCallback($templateOf, []);
                }
                $templates[] = $template;
                $docBlock[] = " * @template $template"
                    . ($templateOf === 'mixed' ? '' : " of $templateOf");
            }
            $docBlock[] = ' *';
            $templates = '<' . implode(',', $templates) . '>';
        }
        $docBlock[] = " * @extends $extends<$service$templates>";
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

        $imports = $this->getImports();

        $blocks = [
            '<?php declare(strict_types=1);',
            "namespace $builderNamespace;",
            implode(PHP_EOL, $imports),
            implode(PHP_EOL, $docBlock) . PHP_EOL
                . ($_class->isAbstract() ? 'abstract ' : ($this->getOptionValue('no-final') ? '' : 'final '))
                . "class $builderClass extends $extends" . PHP_EOL
                . '{'
        ];

        if (!$imports) {
            unset($blocks[3]);
        }

        if (!$builderNamespace) {
            unset($blocks[2]);
        }

        $lines = [implode(PHP_EOL . PHP_EOL, $blocks)];

        array_push(
            $lines,
            ...$this->getStaticGetter('getClassName', "$service::class")
        );

        if ($this->getOption('static-builder')->DefaultValue !== $staticBuilder) {
            array_push(
                $lines,
                '',
                ...$this->getStaticGetter('getStaticBuilder', var_export($staticBuilder, true))
            );
        }

        if ($this->getOption('value-getter')->DefaultValue !== $valueGetter) {
            array_push(
                $lines,
                '',
                ...$this->getStaticGetter('getValueGetter', var_export($valueGetter, true))
            );
        }

        if ($this->getOption('value-checker')->DefaultValue !== $valueChecker) {
            array_push(
                $lines,
                '',
                ...$this->getStaticGetter('getValueChecker', var_export($valueChecker, true))
            );
        }

        if ($this->getOption('terminator')->DefaultValue !== $terminator) {
            array_push(
                $lines,
                '',
                ...$this->getStaticGetter('getTerminator', var_export($terminator, true))
            );
        }

        if ($this->getOption('static-resolver')->DefaultValue !== $staticResolver) {
            array_push(
                $lines,
                '',
                ...$this->getStaticGetter('getStaticResolver', var_export($staticResolver, true))
            );
        }

        /** @var ReflectionParameter $_param */
        foreach ($toDeclare as $name => $_param) {
            $code = [
                'return $this->getWithReference(__FUNCTION__, $variable);'
            ];

            $type = $_param->hasType()
                ? Reflect::getTypeDeclaration($_param->getType(), $classPrefix, $typeNameCallback)
                : '';
            $type = $type ? "$type " : '';
            array_push(
                $lines,
                '',
                ...$this->getMethod($name, $code, ["{$type}&\$variable"], null, $docBlocks[$name], false)
            );
        }

        $lines[] = '}';

        $this->handleOutput($builderClass, $builderNamespace, $lines);
    }

    private function getSummary(
        ?string $summary,
        ?ReflectionProperty $property,
        Closure $typeNameCallback,
        ?string $class = null,
        ?string $name = null,
        ?string $default = null,
        bool $declare = false,
        bool $link = true,
        ?string &$see = null
    ): string {
        if ($summary) {
            $summary = rtrim($summary, '.');
        }
        if ($property) {
            $class = $typeNameCallback($property->getDeclaringClass()->getName(), true);
            $name = $property->getName();
            $param = '';
            $see = $class . '::$' . $name;
        } else {
            $param = "`\$$name` in ";
            $see = $class . '::__construct()';
        }
        if ($default) {
            $defaultPrefix = "$default; ";
            $defaultSuffix = " ($default)";
        } else {
            $defaultSuffix = $defaultPrefix = '';
        }

        return $summary
            ? ($declare
                ? $summary . $defaultSuffix
                : " $summary" . ($link ? " ({$defaultPrefix}see {@see $see})" : $defaultSuffix))
            : (($declare
                ? "Pass a variable to $param$see by reference"
                : ($link
                    ? " See {@see $see}"
                    : ($param ? " Pass \$value to $param$see" : " Set $see")))
                . $defaultSuffix);
    }
}
