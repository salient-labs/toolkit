<?php declare(strict_types=1);

namespace Salient\Sli\Command\Generate;

use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Container\ContainerInterface;
use Salient\Core\AbstractBuilder;
use Salient\Core\Introspector;
use Salient\PHPDoc\Tag\ParamTag;
use Salient\PHPDoc\Tag\TemplateTag;
use Salient\PHPDoc\PHPDoc;
use Salient\PHPDoc\PHPDocUtil;
use Salient\Sli\EnvVar;
use Salient\Utility\Arr;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Salient\Utility\Test;
use Closure;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Generates builders
 */
class GenerateBuilder extends AbstractGenerateCommand
{
    /**
     * Properties and methods that shouldn't be surfaced by the builder
     */
    private const SKIP = [
        // These are displaced by AbstractBuilder
        'apply',
        'build',
        'getB',
        'getContainer',
        'getService',
        'getTerminators',
        'go',
        'if',
        'issetB',
        'resolve',
        'unsetB',
        'withRefB',
        'withValueB',
    ];

    private string $ClassFqcn = '';
    private ?string $BuilderFqcn = null;
    private bool $IncludeProperties = false;
    private bool $IgnoreProperties = false;
    /** @var string[]|null */
    private ?array $Forward = null;
    /** @var string[] */
    private array $NoDeclare = [];
    /** @var string[] */
    private array $Skip = [];

    // --

    /**
     * camelCase name => parameter received by reference, or
     * parameter/property/method with a generic template type
     *
     * @var array<string,ReflectionParameter|ReflectionProperty|ReflectionMethod>
     */
    private array $ToDeclare = [];

    public function getDescription(): string
    {
        return 'Generate a builder';
    }

    protected function getOptionList(): iterable
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
            CliOption::build()
                ->long('properties')
                ->short('r')
                ->description('Include writable properties of <class> in the builder')
                ->bindTo($this->IncludeProperties),
            CliOption::build()
                ->long('no-properties')
                ->short('i')
                ->description(<<<EOF
Ignore properties of <class> when checking for PHP DocBlocks

By default, if a property with the same name as a constructor parameter has a
DocBlock, its description is used in the absence of a parameter description,
even if `-r/--properties` is not given. Use this option to disable this
behaviour.
EOF)
                ->bindTo($this->IgnoreProperties),
            CliOption::build()
                ->long('forward')
                ->short('w')
                ->valueName('method')
                ->description(<<<EOF
Forward calls to <method> from the builder to a new instance of <class>

If no <method> is given, calls to every supported method are forwarded.
EOF)
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->multipleAllowed()
                ->nullable()
                ->bindTo($this->Forward),
            CliOption::build()
                ->long('no-declare')
                ->short('D')
                ->valueName('name')
                ->description(<<<EOF
Do not declare a method for <name>

By default, values passed by reference or with a template in their PHPDoc type
are accepted via a declared method. Use this option to override this behaviour.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->NoDeclare),
            CliOption::build()
                ->long('skip')
                ->short('k')
                ->description('Exclude a property or method from the builder')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->Skip),
            ...$this->getGlobalOptionList('builder'),
        ];
    }

    protected function run(string ...$args)
    {
        $this->startRun();

        $this->Skip = array_merge($this->Skip, self::SKIP);
        $this->ToDeclare = [];

        $classFqcn = $this->requireFqcnOptionValue(
            'class',
            $this->ClassFqcn,
            null,
            $classClass
        );

        $this->requireFqcnOptionValue(
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
        $extends = $this->getFqcnAlias(AbstractBuilder::class);

        $desc = $this->Description === null
            ? "A fluent $classClass factory"
            : $this->Description;

        if ($this->IncludeProperties) {
            $introspector = Introspector::get($this->InputClass->getName());
            $writable = $introspector->getWritableProperties();
            $writable = Arr::combine(
                array_map(
                    fn(string $name) => Str::camel($name),
                    $writable
                ),
                $writable
            );
        } else {
            $writable = [];
        }

        /**
         * camelCase name => parameter
         *
         * @var ReflectionParameter[]
         */
        $_params = [];

        /** @var array<string,array<string,TemplateTag>> */
        $declareTemplates = [];

        /**
         * camelCase name => undelimited docblock
         *
         * @var array<string,string>
         */
        $docBlocks = [];

        /**
         * camelCase name => docblock inserted above return statement
         *
         * @var array<string,string>
         */
        $returnDocBlocks = [];

        /**
         * forwarded method name => has return value?
         *
         * @var array<string,bool>
         */
        $returnsValue = [];

        if ($_constructor = $this->InputClass->getConstructor()) {
            foreach ($_constructor->getParameters() as $_param) {
                $name = Str::camel($_param->getName());
                unset($writable[$name]);
                $_params[$name] = $_param;
                // Variables can't be passed to __call by reference, so this
                // parameter needs to be received via a declared method
                if ($_param->isPassedByReference() && !in_array($name, $this->NoDeclare)) {
                    $this->ToDeclare[$name] = $_param;
                }
            }
        }

        /**
         * camelCase name => non-static public or protected property
         *
         * @var array<string,ReflectionProperty>
         */
        $_allProperties = [];

        /**
         * camelCase name => default property value (`null` if not set)
         *
         * @var array<string,mixed>
         */
        $_defaultProperties = [];

        if (!$this->IgnoreProperties) {
            $defaults = $this->InputClass->getDefaultProperties();
            foreach ($this->InputClass->getProperties(
                ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED
            ) as $_property) {
                if ($_property->isStatic()) {
                    continue;
                }
                $_name = $_property->getName();
                $name = Str::camel($_name);
                $_allProperties[$name] = $_property;
                if (array_key_exists($_name, $defaults)) {
                    $_defaultProperties[$name] = $defaults[$_name];
                }
            }
        }

        /**
         * camelCase name => writable property that isn't also a constructor
         * parameter
         *
         * @var ReflectionProperty[]
         */
        $_properties = [];

        foreach (array_keys($writable) as $name) {
            $_properties[$name] = $_allProperties[$name];
        }

        $_phpDoc = $_constructor ? PHPDoc::forMethod($_constructor) : new PHPDoc();

        $names = array_keys($_params + $_properties);
        foreach ($names as $name) {
            if (in_array($name, $this->Skip)) {
                continue;
            }

            if ($_property = $_properties[$name] ?? null) {
                $phpDoc = PHPDoc::forProperty($_property);
                $propertyFile = $_property->getDeclaringClass()->getFileName();
                $propertyNamespace = $_property->getDeclaringClass()->getNamespaceName();

                $internal = $phpDoc->hasTag('internal');
                $link = !$internal && $phpDoc->hasDetail();

                $vars = $phpDoc->getVars();
                $tag = $vars[0] ?? $vars[$_property->getName()] ?? null;
                $_type = $tag ? $tag->getType() : null;

                if ($_type !== null) {
                    /** @var PHPDoc $phpDoc */
                    $templates = [];
                    $type = $this->getPHPDocTypeAlias(
                        $tag,
                        $phpDoc->getTemplates(),
                        $propertyNamespace,
                        $propertyFile,
                        $templates
                    );
                    if (count($templates) === 1 && !in_array($name, $this->NoDeclare)) {
                        $declareTemplates[$name] = $templates;
                        $this->ToDeclare[$name] ??= $_property;
                    }
                } else {
                    $type = $_property->hasType()
                        ? PHPDocUtil::getTypeDeclaration(
                            $_property->getType(),
                            $classPrefix,
                            fn(string $type): ?string =>
                                $this->getTypeAlias($type, $propertyFile, false)
                        )
                        : '';
                }

                $default = '';
                $defaultText = null;
                switch (Regex::replace('/^(\?|null\|)|\|null$/', '', $type)) {
                    case '\static':
                    case 'static':
                    case '$this':
                        $type = $service;
                        break;
                    case '\self':
                    case 'self':
                        $type = $this->getTypeAlias(
                            $_property->getDeclaringClass()->getName()
                        );
                        break;
                    case 'bool':
                        $default = ' = true';
                        if (array_key_exists($name, $_defaultProperties)) {
                            $defaultValue = $_defaultProperties[$name];
                            if ($defaultValue !== null) {
                                $defaultText = sprintf(
                                    'default: %s',
                                    var_export($defaultValue, true)
                                );
                            }
                        }
                        break;
                }
                $summary = $phpDoc->getSummary();
                if ($summary === null && ($_param = $_params[$name] ?? null) !== null) {
                    $_name = $_param->getName();
                    if (
                        ($tag = $_phpDoc->getParams()[$_name] ?? null)
                        && ($summary = $tag->getDescription()) !== null
                    ) {
                        $summary = Str::collapse($summary);
                    }
                }

                $type = $type !== '' ? "$type " : '';
                $methods[] = " * @method \$this $name($type\$value$default)"
                    . $this->getSummary(
                        $summary,  // Taken from property PHPDoc if set, otherwise constructor PHPDoc if set, otherwise `null`
                        $_property,
                        fn(string $type): ?string =>
                            $this->getTypeAlias($type, $propertyFile, false),
                        null,
                        null,
                        $defaultText,
                        false,
                        $link,
                    );

                continue;
            }

            // If we end up here, we're dealing with a constructor parameter
            /** @var ReflectionMethod $_constructor */
            $maps = $this->getMaps();
            $propertyFile = $_constructor->getFileName();
            $propertyNamespace = $_constructor->getDeclaringClass()->getNamespaceName();
            $declaringClass = $this->getTypeAlias($_constructor->getDeclaringClass()->getName());
            $declare = array_key_exists($name, $this->ToDeclare);

            // If the parameter has a matching property, retrieve its DocBlock
            if ($_property = $_allProperties[$name] ?? null) {
                $phpDoc = PHPDoc::forProperty($_property);
            } else {
                $phpDoc = null;
            }
            $internal = $phpDoc && $phpDoc->hasTag('internal');
            $link = !$internal && $phpDoc && $phpDoc->hasDetail();

            $_param = $_params[$name];
            $_name = $_param->getName();

            $tag = $_phpDoc->getParams()[$_name] ?? null;
            $_type = $tag ? $tag->getType() : null;
            $fromPHPDoc = false;
            if ($_type !== null) {
                /** @var PHPDoc $_phpDoc */
                /** @var ParamTag $tag */
                $templates = [];
                $type = $this->getPHPDocTypeAlias(
                    $tag,
                    $_phpDoc->getTemplates(),
                    $propertyNamespace,
                    $propertyFile,
                    $templates
                );
                if (count($templates) === 1 && !in_array($name, $this->NoDeclare)) {
                    $declareTemplates[$name] = $templates;
                    if (!$declare) {
                        $this->ToDeclare[$name] = $_param;
                        $declare = true;
                    }
                }
                $fromPHPDoc = true;
            } else {
                $type = $_param->hasType()
                    ? PHPDocUtil::getTypeDeclaration(
                        $_param->getType(),
                        $classPrefix,
                        fn(string $type): ?string =>
                            $this->getTypeAlias($type, $propertyFile, false)
                    )
                    : '';
            }

            $default = '';
            $defaultText = null;
            switch (Regex::replace('/^(\?|null\|)|\|null$/', '', $type)) {
                case '\static':
                case 'static':
                case '$this':
                    $type = $service;
                    break;
                case '\self':
                case 'self':
                    $type = $declaringClass;
                    break;
                case 'bool':
                    $default = ' = true';
                    if ($_param->isDefaultValueAvailable()) {
                        $defaultValue = $_param->getDefaultValue();
                        if ($defaultValue !== null) {
                            $defaultText = sprintf(
                                'default: %s',
                                var_export($defaultValue, true)
                            );
                        }
                    }
                    break;
            }

            if (is_a(
                $this->expandAlias($type, $fromPHPDoc ? $propertyFile : null),
                ContainerInterface::class,
                true
            )) {
                $this->setMaps($maps);
                continue;
            }

            if (
                ($tag = $_phpDoc->getParams()[$_name] ?? null)
                && ($summary = $tag->getDescription()) !== null
            ) {
                $summary = Str::collapse($summary);
            } elseif ($phpDoc) {
                $summary = $phpDoc->getSummary();
            } else {
                $summary = null;
            }

            if ($declare) {
                $templates = $declareTemplates[$name] ?? null;
                $param = PHPDocUtil::getParameterTag(
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
                    $summary,  // Taken from constructor PHPDoc if set, otherwise property PHPDoc if set, otherwise `null`
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
                    $returnType = Arr::combine($returnType, $returnType);
                    $i = count($templates) > 1 ? 0 : -1;
                    /** @var TemplateTag $templateTag */
                    foreach ($templates as $template => $templateTag) {
                        do {
                            $T = sprintf('T%s', $i < 0 ? '' : $i);
                            $i++;
                        } while (array_key_exists($T, $this->InputClassTemplates)
                            || array_key_exists(Str::lower($T), $this->AliasMap));
                        $lines[] = (string) $templateTag->withName($T)->withoutVariance();
                        $returnType[$template] = $T;
                        $param = Regex::replace("/(?<!\$|\\\\)\b$template\b/", $T, (string) $param);
                    }
                    $returnType = 'static<' . implode(',', $returnType) . '>';
                    $lines[] = '';
                    $lines[] = $param;
                    $lines[] = "@return $returnType";
                    $returnDocBlocks[$name] = "/** @var $returnType */";
                } else {
                    if ($param) {
                        $lines[] = $param;
                    }
                    $lines[] = '@return static';
                }
                if ($link) {
                    $lines[] = "@see $see";
                }
                $docBlocks[$name] = implode(\PHP_EOL, $lines);
            } else {
                $type = $type !== '' ? "$type " : '';
                $methods[] = " * @method \$this $name($type\$value$default)"
                    . $this->getSummary(
                        $summary,  // Taken from constructor PHPDoc if set, otherwise property PHPDoc if set, otherwise `null`
                        $_property,
                        fn(string $type): ?string =>
                            $this->getTypeAlias($type, $propertyFile, false),
                        $declaringClass,
                        $name,
                        $defaultText,
                        false,
                        $link,
                    );
            }
        }

        if ($this->Forward !== null) {
            $_methods = $this->InputClass->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($_methods as $_method) {
                $name = $_method->getName();
                $phpDoc = PHPDoc::forMethod($_method);

                if ($_method->isConstructor()
                        || $_method->isStatic()
                        || strpos($name, '__') === 0
                        || $phpDoc->hasTag('deprecated')
                        || in_array(Str::camel($name), $names)
                        || in_array(Str::camel(Regex::replace('/^(with|get)/i', '', $name)), $names)
                        || in_array($name, $this->Skip)
                        || ($this->Forward !== [] && !in_array($name, $this->Forward))) {
                    continue;
                }

                $terminators[] = $name;

                $declaringClass = $this->getTypeAlias($_method->getDeclaringClass()->getName());
                $_params = $_method->getParameters();
                $propertyFile = $_method->getFileName() ?: null;
                $propertyNamespace = $_method->getDeclaringClass()->getNamespaceName();

                $declare = (bool) array_filter(
                    $_params,
                    fn(ReflectionParameter $p) =>
                        $p->isPassedByReference()
                ) && !in_array($name, $this->NoDeclare);
                $internal = $phpDoc->hasTag('internal');
                $link = !$internal && $phpDoc->hasDetail();
                $returnsVoid = false;

                if ($declare) {
                    $this->ToDeclare[$name] = $_method;
                }

                $return = $phpDoc->getReturn();
                $_type = $return ? $return->getType() : null;
                if ($_type !== null) {
                    /** @var PHPDoc $phpDoc */
                    $templates = [];
                    $type = $this->getPHPDocTypeAlias(
                        $return,
                        $phpDoc->getTemplates(),
                        $propertyNamespace,
                        $propertyFile,
                        $templates
                    );
                    if ($templates
                            && count($templates) === 1
                            && ($type === 'class-string<' . ($key = array_keys($templates)[0]) . '>'
                                || $type === $key)
                            && !in_array($name, $this->NoDeclare)) {
                        $declareTemplates[$name] = $templates;
                        if (!$declare) {
                            $this->ToDeclare[$name] = $_method;
                            $declare = true;
                        }
                    }
                } else {
                    $type = $_method->hasReturnType()
                        ? PHPDocUtil::getTypeDeclaration(
                            $_method->getReturnType(),
                            $classPrefix,
                            fn(string $type): ?string =>
                                $this->getTypeAlias($type, $propertyFile, false)
                        )
                        : 'mixed';
                }

                switch ($type) {
                    case '\static':
                    case 'static':
                    case '$this':
                        $type = $service;
                        break;
                    case '\self':
                    case 'self':
                        $type = $declaringClass;
                        break;
                    case 'void':
                        $returnsVoid = true;
                        break;
                }

                $summary = $phpDoc->getSummary();

                $params = [];
                foreach ($_params as $_param) {
                    /** @todo: check for templates here? */
                    $tag = $phpDoc->getParams()[$_param->getName()] ?? null;
                    // Override the declared type if defined in the PHPDoc
                    if ($tag && $tag->getType() !== null) {
                        /** @var PHPDoc $phpDoc */
                        $_type = $this->getPHPDocTypeAlias(
                            $tag,
                            $phpDoc->getTemplates(),
                            $propertyNamespace,
                            $propertyFile
                        );
                    } else {
                        $_type = null;
                    }
                    $params[] =
                        $declare
                            ? PHPDocUtil::getParameterTag(
                                $_param,
                                $classPrefix,
                                fn(string $type): ?string =>
                                    $this->getTypeAlias($type, $propertyFile, false),
                                $_type
                            )
                            : PHPDocUtil::getParameterDeclaration(
                                $_param,
                                $classPrefix,
                                fn(string $type): ?string =>
                                    $this->getTypeAlias($type, $propertyFile, false),
                                $_type,
                                null,
                                true
                            );
                }

                if ($declare) {
                    $params = array_filter($params);
                    $return = ($type && (!$_method->hasReturnType()
                            || PHPDocUtil::getTypeDeclaration(
                                $_method->getReturnType(),
                                $classPrefix,
                                fn(string $type): ?string =>
                                    $this->getTypeAlias($type, $propertyFile, false)
                            ) !== $type))
                        ? "@return $type"
                        : '';

                    $lines = [];
                    $lines[] = $this->getSummary(
                        $summary,
                        $_method,
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
                    if ($internal) {
                        $lines[] = '@internal';
                    }
                    if ($params) {
                        array_push($lines, ...$params);
                    }
                    if ($return) {
                        $lines[] = $return;
                    }
                    if ($link) {
                        $lines[] = "@see $see";
                    }
                    $docBlocks[$name] = implode(\PHP_EOL, $lines);
                    $returnsValue[$name] = !$returnsVoid;
                } else {
                    $methods[] = " * @method $type $name("
                        . str_replace(\PHP_EOL, \PHP_EOL . ' * ', implode(', ', $params)) . ')'
                        . $this->getSummary(
                            $summary,
                            $_method,
                            fn(string $type): ?string =>
                                $this->getTypeAlias($type, $propertyFile, false),
                            $declaringClass,
                            $name,
                            null,
                            false,
                            $link
                        );
                }
            }
        }

        $methods = implode(\PHP_EOL, $methods ?? []);

        $docBlock[] = '/**';
        if ($desc) {
            $docBlock[] = " * $desc";
            $docBlock[] = ' *';
        }
        $docBlock[] = $methods;
        if ($this->ApiTag) {
            $docBlock[] = ' *';
            $docBlock[] = ' * @api';
        }
        if ($this->InputClassTemplates) {
            $docBlock[] = ' *';
            foreach ($this->InputClassTemplates as $template => $tag) {
                $tagType = $tag->getType();
                if (
                    $tagType !== null
                    && !Test::isBuiltinType($tagType)
                    && !array_key_exists($tagType, $this->InputClassTemplates)
                ) {
                    $tag = $tag->withType($this->getPHPDocTypeAlias(
                        $tag,
                        [],
                        $this->InputClass->getNamespaceName(),
                        $this->InputClass->getFileName()
                    ));
                }
                $docBlock[] = " * $tag";
            }
        }
        $docBlock[] = ' *';
        $docBlock[] = " * @extends $extends<$service{$this->InputClassType}>";
        $docBlock[] = ' *';
        $docBlock[] = ' * @generated';
        $docBlock[] = ' */';

        $imports = $this->generateImports();

        $blocks = [
            '<?php declare(strict_types=1);',
            "namespace $builderNamespace;",
            implode(\PHP_EOL, $imports),
            implode(\PHP_EOL, $docBlock) . \PHP_EOL
                . "final class $builderClass extends $extends" . \PHP_EOL
                . '{'
        ];

        if (!$imports) {
            unset($blocks[3]);
        }

        if (($builderNamespace ?? '') === '') {
            unset($blocks[2]);
        }

        $lines = [implode(\PHP_EOL . \PHP_EOL, $blocks)];

        array_push(
            $lines,
            ...$this->indent($this->generateGetter(
                'getService',
                "$service::class",
                '@internal',
                'string',
                self::VISIBILITY_PROTECTED,
            )),
        );

        if (isset($terminators)) {
            array_push(
                $lines,
                '',
                ...$this->indent($this->generateGetter(
                    'getTerminators',
                    $this->code($terminators),
                    '@internal',
                    'array',
                    self::VISIBILITY_PROTECTED,
                )),
            );
        }

        foreach ($this->ToDeclare as $name => $_param) {
            if ($_param instanceof ReflectionMethod) {
                $_params = $_param->getParameters();
                $return = $returnsValue[$name] ? 'return ' : '';
                $code = sprintf(
                    '%s$this->build()->%s(%s);',
                    $return,
                    $name,
                    implode(', ', array_map(
                        fn(ReflectionParameter $p) =>
                            ($p->isVariadic() ? '...' : '') . '$' . $p->getName(),
                        $_params
                    ))
                );
                array_push(
                    $lines,
                    '',
                    ...$this->indent($this->generateMethod(
                        $name,
                        $code,
                        $_params,
                        $_param->getReturnType(),
                        $docBlocks[$name],
                        false
                    ))
                );
                continue;
            }

            $code = [];

            if (isset($returnDocBlocks[$name])) {
                $code[] = $returnDocBlocks[$name];
            }

            if ($_param instanceof ReflectionParameter && $_param->isPassedByReference()) {
                $code[] = 'return $this->withRefB(__FUNCTION__, $variable);';
                $param = '&$variable';
            } else {
                $code[] = 'return $this->withValueB(__FUNCTION__, $value);';
                $param = '$value';
            }

            $type = $_param->hasType()
                ? PHPDocUtil::getTypeDeclaration(
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
            $type = $type !== '' ? "$type " : '';
            array_push(
                $lines,
                '',
                ...$this->indent($this->generateMethod(
                    $name,
                    $code,
                    ["{$type}{$param}"],
                    null,
                    $docBlocks[$name],
                    false
                ))
            );
        }

        $lines[] = '}';

        $this->handleOutput($lines);
    }

    /**
     * @param ReflectionProperty|ReflectionMethod|null $member
     */
    private function getSummary(
        ?string $summary,
        $member,
        Closure $typeNameCallback,
        ?string $class = null,
        ?string $name = null,
        ?string $default = null,
        bool $declare = false,
        bool $link = true,
        ?string &$see = null
    ): string {
        if ($summary !== null) {
            $summary = rtrim($summary, '.');
        }
        $byRef = false;
        if ($declare && $name !== null) {
            $declaring = $this->ToDeclare[$name] ?? null;
            if ($declaring instanceof ReflectionParameter) {
                $byRef = $declaring->isPassedByReference();
            }
        }
        if ($member) {
            $class = $typeNameCallback($member->getDeclaringClass()->getName());
            $name = $member->getName();
            $param = '';
            $see =
                $member instanceof ReflectionMethod
                    ? $class . '::' . $name . '()'
                    : $class . '::$' . $name;
        } else {
            $param = $declare ? "\$$name in " : "`\$$name` in ";
            $see = $class . '::__construct()';
        }
        if ($default !== null) {
            $defaultPrefix = "$default; ";
            $defaultSuffix = " ($default)";
        } else {
            $defaultSuffix = $defaultPrefix = '';
        }

        return
            $summary !== null
                ? ($declare
                    ? $summary . $defaultSuffix
                    : " $summary"
                        . ($link
                            ? " ({$defaultPrefix}see {@see $see})"
                            : $defaultSuffix))
                : (($declare
                    ? ($member instanceof ReflectionMethod
                        ? "Call $see on a new instance"
                        : ($byRef
                            ? "Pass a variable to $param$see by reference"
                            : "Pass a value to $param$see"))
                    : ($link
                        ? " See {@see $see}"
                        : ($member instanceof ReflectionMethod
                            ? " Call $see on a new instance"
                            : ($param !== ''
                                ? " Pass \$value to $param$see"
                                : " Set $see"))))
                    . $defaultSuffix);
    }
}
