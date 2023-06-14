<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Closure;
use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\CliOption;
use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Reflect;
use Lkrms\LkUtil\Catalog\EnvVar;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\Support\Introspector;
use Lkrms\Support\PhpDoc\PhpDoc;
use Lkrms\Support\PhpDoc\PhpDocTemplateTag;
use Lkrms\Utility\Test;
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
    private ?string $StaticBuilder;
    private ?string $ValueGetter;
    private ?string $ValueChecker;
    private ?string $Terminator;
    private ?string $StaticResolver;
    private CliOption $StaticBuilderOption;
    private CliOption $ValueGetterOption;
    private CliOption $ValueCheckerOption;
    private CliOption $TerminatorOption;
    private CliOption $StaticResolverOption;

    /**
     * @var string[]
     */
    private array $SkipProperties = [];

    public function description(): string
    {
        return 'Generate an object factory with a fluent interface';
    }

    protected function getOptionList(): array
    {
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
                ->valueName('builder_class')
                ->description('The class to generate')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->bindTo($this->BuilderFqcn),
            $this->StaticBuilderOption =
                CliOption::build()
                    ->long('static-builder')
                    ->valueName('method')
                    ->description('The static method that returns a new builder')
                    ->optionType(CliOptionType::VALUE)
                    ->defaultValue('build')
                    ->valueCallback([Convert::class, 'toCamelCase'])
                    ->bindTo($this->StaticBuilder)
                    ->go(),
            $this->ValueGetterOption =
                CliOption::build()
                    ->long('value-getter')
                    ->valueName('method')
                    ->description('The method that returns a value if it has been set')
                    ->optionType(CliOptionType::VALUE)
                    ->defaultValue('get')
                    ->valueCallback([Convert::class, 'toCamelCase'])
                    ->bindTo($this->ValueGetter)
                    ->go(),
            $this->ValueCheckerOption =
                CliOption::build()
                    ->long('value-checker')
                    ->valueName('method')
                    ->description('The method that returns true if a value has been set')
                    ->optionType(CliOptionType::VALUE)
                    ->defaultValue('isset')
                    ->valueCallback([Convert::class, 'toCamelCase'])
                    ->bindTo($this->ValueChecker)
                    ->go(),
            $this->TerminatorOption =
                CliOption::build()
                    ->long('terminator')
                    ->valueName('method')
                    ->description('The method that terminates the interface')
                    ->optionType(CliOptionType::VALUE)
                    ->defaultValue('go')
                    ->valueCallback([Convert::class, 'toCamelCase'])
                    ->bindTo($this->Terminator)
                    ->go(),
            $this->StaticResolverOption =
                CliOption::build()
                    ->long('static-resolver')
                    ->valueName('method')
                    ->description('The static method that resolves unterminated builders')
                    ->optionType(CliOptionType::VALUE)
                    ->defaultValue('resolve')
                    ->valueCallback([Convert::class, 'toCamelCase'])
                    ->bindTo($this->StaticResolver)
                    ->go(),
            ...$this->getOutputOptionList('builder'),
        ];
    }

    protected function run(string ...$args)
    {
        $this->reset();

        $this->SkipProperties = [
            $this->StaticBuilder,
            $this->ValueGetter,
            $this->ValueChecker,
            $this->Terminator,
            $this->StaticResolver,
        ];

        $classFqcn = $this->getRequiredFqcnOptionValue(
            'class',
            $this->ClassFqcn,
            null,
            $classClass
        );

        $this->getRequiredFqcnOptionValue(
            'builder',
            $this->BuilderFqcn ?: $classFqcn . 'Builder',
            EnvVar::NS_BUILDER,
            $builderClass,
            $builderNamespace
        );

        $this->assertClassIsInstantiable($classFqcn);

        $this->OutputClass = $builderClass;
        $this->OutputNamespace = $builderNamespace;

        $this->loadInputClass($classFqcn);

        $classPrefix = $this->getClassPrefix();

        $service = $this->getFqcnAlias($classFqcn, $classClass);
        $extends = $this->getFqcnAlias(Builder::class);
        $container = $this->getFqcnAlias(IContainer::class);

        $desc = $this->OutputDescription === null
            ? "A fluent interface for creating $classClass objects"
            : $this->OutputDescription;

        $writable = Introspector::get($this->InputClass->getName())->getWritableProperties();
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
        /** @var array<string,array<string,PhpDocTemplateTag>> */
        $declareTemplates = [];
        $docBlocks = [];
        if ($_constructor = $this->InputClass->getConstructor()) {
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
        $defaults = $this->InputClass->getDefaultProperties();
        foreach ($this->InputClass->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED) as $_property) {
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
        foreach (array_keys($writable) as $name) {
            $_properties[$name] = $_property = $_allProperties[$name];
        }

        $_docBlocks = Reflect::getAllMethodDocComments($_constructor, $classDocBlocks);
        $_phpDoc = PhpDoc::fromDocBlocks($_docBlocks, $classDocBlocks, $_constructor->getName() . '()');

        $names = array_keys($_params + $_properties);
        //sort($names);
        $methods = [
            " * @method static \$this {$this->StaticBuilder}(?$container \$container = null) Create a new $builderClass (syntactic sugar for 'new $builderClass()')",
        ];
        foreach ($names as $name) {
            if (in_array($name, $this->SkipProperties)) {
                continue;
            }

            if ($_property = $_properties[$name] ?? null) {
                $_docBlocks = Reflect::getAllPropertyDocComments($_property, $classDocBlocks);
                $phpDoc = PhpDoc::fromDocBlocks($_docBlocks, $classDocBlocks, '$' . $_property->getName());
                $propertyFile = $_property->getDeclaringClass()->getFileName();
                $propertyNamespace = $_property->getDeclaringClass()->getNamespaceName();

                $internal = (bool) ($phpDoc->TagsByName['internal'] ?? null);
                $link = !$internal && $phpDoc && $phpDoc->hasDetail();

                foreach ([
                    $phpDoc->Vars[0] ?? null,
                    $phpDoc->Vars[$_property->getName()] ?? null,
                ] as $tag) {
                    if ($_type = $tag->Type ?? null) {
                        break;
                    }
                }
                if ($_type) {
                    $templates = [];
                    $type = $this->getPhpDocTypeAlias(
                        $tag,
                        $phpDoc->Templates,
                        $propertyNamespace,
                        $propertyFile,
                        $templates
                    );
                    if ($templates &&
                        count($templates) === 1 &&
                        ($type === 'class-string<' . ($key = array_keys($templates)[0]) . '>' ||
                            $type === $key)) {
                        $declareTemplates[$name] = $templates;
                        $toDeclare[$name] = $toDeclare[$name] ?? $_property;
                    }
                } else {
                    $type = $_property->hasType()
                        ? Reflect::getTypeDeclaration(
                            $_property->getType(),
                            $classPrefix,
                            fn(string $type): ?string =>
                                $this->getTypeAlias($type, $propertyFile, false)
                        )
                        : '';
                }

                $default = '';
                $defaultText = null;
                switch (preg_replace('/^(\?|null\|)|\|null$/', '', $type)) {
                    case 'static':
                    case '$this':
                        $type = $service;
                        break;
                    case 'self':
                        $type = $this->getTypeAlias(
                            $_property->getDeclaringClass()->getName()
                        );
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
                        fn(string $type): ?string =>
                            $this->getTypeAlias($type, $propertyFile, false),
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
            $declaringClass = $this->getTypeAlias($_constructor->getDeclaringClass()->getName());
            $declare = array_key_exists($name, $toDeclare);

            // If the parameter has a matching property, retrieve its DocBlock
            if ($_property = $_allProperties[$name] ?? null) {
                $_docBlocks = Reflect::getAllPropertyDocComments($_property, $classDocBlocks);
                $phpDoc = PhpDoc::fromDocBlocks($_docBlocks, $classDocBlocks, '$' . $_property->getName());
            } else {
                $phpDoc = null;
            }
            $internal = (bool) ($phpDoc->TagsByName['internal'] ?? null);
            $link = !$internal && $phpDoc && $phpDoc->hasDetail();

            $_param = $_params[$name];
            $_name = $_param->getName();

            $tag = $_phpDoc->Params[$_name] ?? null;
            if ($_type = $tag->Type ?? null) {
                $templates = [];
                $type = $this->getPhpDocTypeAlias(
                    $tag,
                    $_phpDoc->Templates,
                    $propertyNamespace,
                    $propertyFile,
                    $templates
                );
                if ($templates &&
                    count($templates) === 1 &&
                    ($type === 'class-string<' . ($key = array_keys($templates)[0]) . '>' ||
                        $type === $key)) {
                    $declareTemplates[$name] = $templates;
                    if (!$declare) {
                        $toDeclare[$name] = $_param;
                        $declare = true;
                    }
                }
            } else {
                $type = $_param->hasType()
                    ? Reflect::getTypeDeclaration(
                        $_param->getType(),
                        $classPrefix,
                        fn(string $type): ?string =>
                            $this->getTypeAlias($type, $propertyFile, false)
                    )
                    : '';
            }

            $default = '';
            $defaultText = null;
            switch (preg_replace('/^(\?|null\|)|\|null$/', '', $type)) {
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
                $templates = $declareTemplates[$name] ?? null;
                $param = Reflect::getParameterPhpDoc(
                    $_param,
                    $classPrefix,
                    fn(string $type): ?string =>
                        $this->getTypeAlias($type, $propertyFile, false),
                    $_type,
                    $_param->isPassedByReference() ? 'variable' : 'value',
                    null,
                    (bool) $templates
                );

                $lines = [];
                $lines[] = $this->getSummary(
                    $summary,
                    $_property,
                    fn(string $type): ?string =>
                        $this->getTypeAlias($type, $propertyFile, false),
                    $declaringClass,
                    $name,
                    null,
                    true,
                    $link,
                    $see
                );
                $lines[] = '';
                if ($templates) {
                    $returnType = array_keys($this->InputClassTemplates);
                    $returnType = array_combine($returnType, $returnType);
                    $i = count($templates) > 1 ? 0 : -1;
                    /** @var PhpDocTemplateTag $templateTag */
                    foreach ($templates as $template => $templateTag) {
                        do {
                            $T = sprintf('T%s', $i < 0 ? '' : $i);
                            $i++;
                        } while (array_key_exists($T, $this->InputClassTemplates) ||
                            array_key_exists(strtolower($T), $this->AliasMap));
                        $templateTag = clone $templateTag;
                        $templateTag->Name = $T;
                        $templateTag->Variance = null;
                        $lines[] = (string) $templateTag;
                        $returnType[$template] = $T;
                        $param = preg_replace("/(?<!\$|\\\\)\b$template\b/", $T, $param);
                    }
                    $lines[] = $param;
                    $lines[] = '@return $this<' . implode(',', $returnType) . '>';
                } else {
                    if ($param) {
                        $lines[] = $param;
                    }
                    $lines[] = '@return $this';
                }
                if ($link) {
                    $lines[] = "@see $see";
                }
                $docBlocks[$name] = implode(PHP_EOL, $lines);
            } else {
                $type = $type ? "$type " : '';
                $methods[] = " * @method \$this $name($type\$value$default)"
                    . $this->getSummary(
                        $summary,
                        $_property,
                        fn(string $type): ?string =>
                            $this->getTypeAlias($type, $propertyFile, false),
                        $declaringClass,
                        $name,
                        $defaultText,
                        false,
                        $link
                    );
            }
        }
        $methods[] = " * @method mixed {$this->ValueGetter}(string \$name) The value of \$name if applied to the unresolved $classClass by calling \$name(), otherwise null";
        $methods[] = " * @method bool {$this->ValueChecker}(string \$name) True if a value for \$name has been applied to the unresolved $classClass by calling \$name()";
        $methods[] = " * @method $service {$this->Terminator}() Get a new $classClass object";
        $methods[] = " * @method static $service {$this->StaticResolver}($service|$builderClass \$object) Resolve a $builderClass or $classClass object to a $classClass object";
        $methods = implode(PHP_EOL, $methods);

        $docBlock[] = '/**';
        if ($desc) {
            $docBlock[] = " * $desc";
            $docBlock[] = ' *';
        }
        if ($this->InputClassTemplates) {
            foreach ($this->InputClassTemplates as $template => $tag) {
                $tag = clone $tag;
                if (!Test::isPhpReservedWord($tag->Type) &&
                        !array_key_exists($tag->Type, $this->InputClassTemplates)) {
                    $tag->Type = $this->getPhpDocTypeAlias(
                        $tag,
                        [],
                        $this->InputClass->getNamespaceName(),
                        $this->InputClass->getFileName()
                    );
                }
                $docBlock[] = " * $tag";
            }
            $docBlock[] = ' *';
        }
        $docBlock[] = $methods;
        $docBlock[] = ' *';
        $docBlock[] = " * @uses $service";
        $docBlock[] = ' *';
        $docBlock[] = " * @extends $extends<$service{$this->InputClassType}>";
        if (!$this->NoMetaTags) {
            $command = $this->getEffectiveCommandLine(true, [
                'stdout' => null,
                'force' => null,
            ]);
            $program = array_shift($command);
            $docBlock[] = ' *';
            $docBlock[] = ' * @generated by ' . $program;
            $docBlock[] = ' * @salient-generate-command ' . implode(' ', $command);
        }
        $docBlock[] = ' */';

        $imports = $this->generateImports();

        $blocks = [
            '<?php declare(strict_types=1);',
            "namespace $builderNamespace;",
            implode(PHP_EOL, $imports),
            implode(PHP_EOL, $docBlock) . PHP_EOL
                . "final class $builderClass extends $extends" . PHP_EOL
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
            ...$this->generateGetter('getClassName', "$service::class", [
                '@internal',
                sprintf('@return class-string<%s>', "$service{$this->InputClassType}"),
            ])
        );

        $getters = [
            'getStaticBuilder' => [$this->StaticBuilderOption, $this->StaticBuilder],
            'getValueGetter' => [$this->ValueGetterOption, $this->ValueGetter],
            'getValueChecker' => [$this->ValueCheckerOption, $this->ValueChecker],
            'getTerminator' => [$this->TerminatorOption, $this->Terminator],
            'getStaticResolver' => [$this->StaticResolverOption, $this->StaticResolver],
        ];

        foreach ($getters as $getter => [$option, $methodName]) {
            if ($option->DefaultValue !== $methodName) {
                array_push($lines, '', ...$this->generateGetter($getter, var_export($methodName, true)));
            }
        }

        /** @var ReflectionParameter $_param */
        foreach ($toDeclare as $name => $_param) {
            if ($_param instanceof ReflectionParameter && $_param->isPassedByReference()) {
                $code = 'return $this->getWithReference(__FUNCTION__, $variable);';
                $param = '&$variable';
            } else {
                $code = 'return $this->getWithValue(__FUNCTION__, $value);';
                $param = '$value';
            }

            $type = $_param->hasType()
                ? Reflect::getTypeDeclaration(
                    $_param->getType(),
                    $classPrefix,
                    fn(string $type): ?string =>
                        $this->getTypeAlias(
                            $type,
                            $_param->getDeclaringClass()->getFileName(),
                            false
                        )
                )
                : '';
            $type = $type ? "$type " : '';
            array_push(
                $lines,
                '',
                ...$this->generateMethod($name, [$code], ["{$type}{$param}"], null, $docBlocks[$name], false)
            );
        }

        $lines[] = '}';

        $this->handleOutput($lines);
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
