<?php declare(strict_types=1);

namespace Salient\Sli\Command;

use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Contract\Cli\CliOptionVisibility;
use Salient\Core\Facade\Console;
use Salient\PHPDoc\PHPDoc;
use Salient\PHPDoc\PHPDocUtil;
use Salient\Sli\Internal\Data\ClassData;
use Salient\Sli\Internal\Data\ConstantData;
use Salient\Sli\Internal\Data\MethodData;
use Salient\Sli\Internal\Data\NamespaceData;
use Salient\Sli\Internal\Data\PropertyData;
use Salient\Sli\Internal\NavigableToken;
use Salient\Sli\Internal\TokenExtractor;
use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\Arr;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Json;
use Salient\Utility\Reflect;
use Salient\Utility\Str;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;

class AnalyseClass extends AbstractCommand
{
    /** @var string[] */
    private array $Path = [];
    private string $Format = '';
    private string $Exclude = '';
    /** @var string[] */
    private array $Autoload = [];
    private bool $Debug = false;

    public function getDescription(): string
    {
        return 'Analyse PHP classes';
    }

    protected function getOptionList(): iterable
    {
        return [
            CliOption::build()
                ->name('path')
                ->description('Paths to analyse')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueType(CliOptionValueType::PATH)
                ->required()
                ->multipleAllowed()
                ->unique()
                ->bindTo($this->Path),
            CliOption::build()
                ->long('format')
                ->short('f')
                ->valueName('format')
                ->description('Output format')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(['json', 'csv', 'md'])
                ->defaultValue('json')
                ->bindTo($this->Format),
            CliOption::build()
                ->long('exclude')
                ->short('X')
                ->valueName('regex')
                ->description('Regular expression for paths to exclude')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('/\/(\.git|\.hg|\.svn|_?build|dist|stubs?|vendor)\/$/')
                ->bindTo($this->Exclude),
            CliOption::build()
                ->long('autoload')
                ->short('A')
                ->valueName('file')
                ->description('Include a PHP script before loading classes')
                ->optionType(CliOptionType::VALUE)
                ->valueType(CliOptionValueType::FILE)
                ->multipleAllowed()
                ->bindTo($this->Autoload),
            CliOption::build()
                ->long('debug')
                ->description('Print debug information')
                ->visibility(CliOptionVisibility::ALL_EXCEPT_SYNOPSIS)
                ->bindTo($this->Debug),
        ];
    }

    protected function run(string ...$args)
    {
        if ($this->Debug) {
            Env::setDebug(true);
            $this->App->logOutput();
        } else {
            $this->Debug = Env::getDebug();
        }

        Console::registerStderrTarget();

        foreach ($this->Autoload as $file) {
            require_once $file;
        }

        $dirs = [];
        $files = [];
        foreach ($this->Path as $path) {
            $key = File::getIdentifier($path);
            if (is_dir($path)) {
                $dirs[$key] ??= $path;
            } else {
                $files[$key] ??= $path;
            }
        }

        if ($dirs) {
            $dirs = array_values($dirs);
            $finder = File::find()
                ->in(...$dirs)
                ->include('/\.php$/')
                ->exclude($this->Exclude);
            foreach ($finder as $file) {
                $file = (string) $file;
                $key = File::getIdentifier($file);
                $files[$key] ??= $file;
            }
        }

        $json = $this->Format === 'json';
        $csv = $this->Format === 'csv';
        $md = $this->Format === 'md';

        Console::info(Inflect::format($files, 'Analysing {{#}} {{#:file}}'));

        /** @var array<class-string,ClassData> */
        $index = [];
        /** @var array<string,NamespaceData> */
        $tree = [];
        $data = [];

        foreach ($files as $file) {
            $extractors = TokenExtractor::fromFile($file)->getNamespaces();
            foreach ($extractors as $ns => $extractor) {
                $delimiter = $ns === '' ? '' : '\\';
                foreach ([
                    'class' => ['getClasses', 'Classes'],
                    'interface' => ['getInterfaces', 'Interfaces'],
                    'trait' => ['getTraits', 'Traits'],
                    'enum' => ['getEnums', 'Enums'],
                ] as $type => [$extractorMethod, $nsProperty]) {
                    $existsFunction = $type . '_exists';
                    /** @var iterable<string,TokenExtractor> */
                    $classes = $extractor->$extractorMethod();
                    foreach ($classes as $class => $extractor) {
                        /** @var NavigableToken */
                        $token = $extractor->getClassToken();
                        /** @var class-string */
                        $fqcn = $ns . $delimiter . $class;
                        $_fqcn = Get::fqcn($fqcn);

                        if (isset($index[$_fqcn])) {
                            continue;
                        }

                        if (!$existsFunction($fqcn)) {
                            Console::warn(
                                sprintf('Cannot load %s:', $type),
                                sprintf('%s (in %s:%d)', $fqcn, $file, $token->line)
                            );
                            continue;
                        }

                        $_class = new ReflectionClass($fqcn);
                        $_file = $_class->getFileName();
                        if ($_file === false || !File::same($_file, $file)) {
                            if ($_file === false) {
                                $_file = '<internal>';
                            }
                            Console::warn(
                                sprintf('%s loaded from:', $type),
                                sprintf('%s (expected %s)', $_file, $file)
                            );
                            continue;
                        }

                        $classData = $this->getClassData($_class, $extractor);
                        $classData->File = $file;
                        $index[$_fqcn] = $classData;

                        if ($csv) {
                            $row = array_merge(
                                [
                                    'file' => null,
                                    'line' => null,
                                    'lines' => null,
                                    'type' => $type,
                                    'name' => $fqcn,
                                    'namespace' => $ns,
                                    'shortName' => $class,
                                ],
                                Arr::unset(
                                    $classData->jsonSerialize(),
                                    'constants',
                                    'properties',
                                    'methods',
                                ),
                                [
                                    'templates' => $this->implodeWithKeys(', ', $classData->Templates),
                                    'extends' => implode(', ', $classData->Extends),
                                    'implements' => implode(', ', $classData->Implements),
                                    'uses' => implode(', ', $classData->Uses),
                                    'modifiers' => implode(' ', $classData->Modifiers),
                                ],
                            );

                            foreach ([
                                'constant' => $classData->Constants,
                                'property' => $classData->Properties,
                                'method' => $classData->Methods,
                            ] as $memberType => $members) {
                                foreach ($this->applyMemberData($row, $memberType, $members) as $memberRow) {
                                    $data[] = $memberRow;
                                }
                            }
                        } else {
                            $tree[$ns] ??= new NamespaceData($ns);
                            $tree[$ns]->{$nsProperty}[$class] = $classData;
                        }

                        if ($this->Debug) {
                            Console::debug(sprintf('Loaded %s:', $type), $fqcn);
                        }
                    }
                }
            }
        }

        if ($csv) {
            /** @var array<array{name:string,m_type:string,m_name:string,m_line:int|null}> $data */
            usort(
                $data,
                fn($a, $b) =>
                    strcasecmp($a['name'], $b['name'])
                        ?: $a['m_type'] <=> $b['m_type']
                        ?: ($a['m_line'] ?? \PHP_INT_MAX) <=> ($b['m_line'] ?? \PHP_INT_MAX)
                        ?: strcasecmp($a['m_name'], $b['m_name']),
            );
        } else {
            uksort(
                $tree,
                fn($a, $b) =>
                    strcasecmp($a, $b),
            );
            foreach ($tree as $nsData) {
                $this->sortClasses($nsData->Classes);
                $this->sortClasses($nsData->Interfaces);
                $this->sortClasses($nsData->Traits);
                $this->sortClasses($nsData->Enums);
            }
        }

        $stdout = Console::getStdoutTarget();
        $tty = $stdout->isTty();

        if ($json) {
            $eol = $tty ? $stdout->getEol() : \PHP_EOL;
            echo Json::prettyPrint($tree, 0, $eol) . $eol;
        } elseif ($csv) {
            $eol = $tty ? $stdout->getEol() : "\r\n";
            File::writeCsv('php://output', $data, true, null, null, $count, $eol, !$tty, !$tty);
        } elseif ($md) {
            $eol = $tty ? $stdout->getEol() : \PHP_EOL;
            /** @var string[] */
            $block = [];
            $blockPrefix = '';
            $printBlock = function (?string $line = null) use ($eol, &$block, &$blockPrefix) {
                if ($line !== null) {
                    $block[] = $line;
                }
                echo $blockPrefix . str_replace(
                    "\n",
                    $eol . $blockPrefix,
                    implode("\n", $block),
                ) . $eol . $eol;
                $block = [];
                $blockPrefix = '';
            };

            foreach ($tree as $ns => $nsData) {
                $printBlock('## ' . Str::coalesce($ns, 'Global'));

                foreach ([
                    'class' => ['Classes', 'Classes'],
                    'interface' => ['Interfaces', 'Interfaces'],
                    'trait' => ['Traits', 'Traits'],
                    'enum' => ['Enums', 'Enumerations'],
                ] as $type => [$nsProperty, $typeHeading]) {
                    /** @var array<string,ClassData> */
                    $classes = $nsData->{$nsProperty};
                    if (!$classes) {
                        continue;
                    }

                    $printBlock("### {$typeHeading}");

                    foreach ($classes as $className => $classData) {
                        $printBlock("#### {$className}");

                        $meta = [];
                        if ($classData->Lines !== null) {
                            $meta[] = Inflect::format($classData->Lines, '{{#}} {{#:line}}');
                        }

                        if ($meta = array_merge($meta, array_keys(array_filter([
                            'no DocBlock' => !$classData->HasDocComment,
                            'in API' => $classData->Api,
                            'internal' => $classData->Internal,
                            'deprecated' => $classData->Deprecated,
                        ])))) {
                            $printBlock('<small>(' . implode(', ', $meta) . ')</small>');
                        }

                        if ($classData->Summary !== null) {
                            $printBlock($classData->Summary);
                        }

                        $block[] = '```php';
                        $block[] = Arr::implode(' ', [
                            implode(' ', $classData->Modifiers),
                            $type,
                            $className . $this->implodeTemplates($classData->Templates, $eol),
                            Arr::implode("\n", [
                                $this->implodeInherited('extends', $classData->Extends, $eol),
                                $this->implodeInherited('implements', $classData->Implements, $eol),
                            ], ''),
                        ], '');
                        $printBlock('```');

                        if ($classData->Uses) {
                            $printBlock('##### Uses');

                            foreach ($classData->Uses as $trait) {
                                $block[] = "- `{$trait}`";
                            }
                            $printBlock();
                        }

                        if ($classData->Constants) {
                            $printBlock('##### Constants');

                            foreach ($classData->Constants as $constantName => $constantData) {
                                $block[] = "###### {$constantName}";
                                $block[] = '';

                                $meta = [];
                                if ($constantData->Inherited || $constantData->InheritedFrom) {
                                    $blockPrefix = '> ';
                                    if ($original = $constantData->InheritedFrom) {
                                        $meta[] = "from `{$original[0]}::{$original[1]}`";
                                    }
                                }

                                if ($meta = array_merge($meta, array_keys(array_filter([
                                    'in API' => $constantData->Api,
                                    'internal' => $constantData->Internal,
                                    'deprecated' => $constantData->Deprecated,
                                ])))) {
                                    $block[] = '<small>(' . implode(', ', $meta) . ')</small>';
                                    $block[] = '';
                                }

                                if ($constantData->Summary !== null) {
                                    $block[] = $constantData->Summary;
                                    $block[] = '';
                                }

                                $block[] = '```php';
                                $block[] = Arr::implode(' ', [
                                    implode(' ', $constantData->Modifiers),
                                    'const',
                                    $constantData->Type,
                                    "{$constantName} = {$constantData->Value}",
                                ], '');
                                $block[] = '```';

                                $printBlock();
                            }
                        }

                        if ($classData->Properties) {
                            $printBlock('##### Properties');

                            foreach ($classData->Properties as $propertyName => $propertyData) {
                                $block[] = "###### {$propertyName}";
                                $block[] = '';

                                $meta = [];
                                if ($propertyData->Inherited || $propertyData->InheritedFrom) {
                                    $blockPrefix = '> ';
                                    if ($original = $propertyData->InheritedFrom) {
                                        $meta[] = "from `{$original[0]}::{$original[1]}`";
                                    }
                                }

                                if ($meta = array_merge($meta, array_keys(array_filter([
                                    'in API' => $propertyData->Api,
                                    'internal' => $propertyData->Internal,
                                    'deprecated' => $propertyData->Deprecated,
                                ])))) {
                                    $block[] = '<small>(' . implode(', ', $meta) . ')</small>';
                                    $block[] = '';
                                }

                                if ($propertyData->Summary !== null) {
                                    $block[] = $propertyData->Summary;
                                    $block[] = '';
                                }

                                $block[] = '```php';
                                $block[] = Arr::implode(' ', [
                                    Str::coalesce(implode(' ', $propertyData->Modifiers), 'var'),
                                    $propertyData->Type,
                                    "\${$propertyName}" . (
                                        $propertyData->DefaultValue === null
                                            ? ''
                                            : " = {$propertyData->DefaultValue}"
                                    ),
                                ], '');
                                $block[] = '```';

                                $printBlock();
                            }
                        }

                        if ($classData->Methods) {
                            $printBlock('##### Methods');

                            foreach ($classData->Methods as $methodName => $methodData) {
                                $block[] = "###### {$methodName}()";
                                $block[] = '';

                                $meta = [];
                                if ($methodData->Inherited || $methodData->InheritedFrom) {
                                    $blockPrefix = '> ';
                                    if ($original = $methodData->InheritedFrom) {
                                        $meta[] = "from `{$original[0]}::{$original[1]}()`";
                                    }
                                } else {
                                    if ($methodData->Lines !== null) {
                                        $meta[] = Inflect::format($methodData->Lines, '{{#}} {{#:line}}');
                                    }
                                    if (!$methodData->HasDocComment) {
                                        $meta[] = 'no DocBlock';
                                    }
                                }

                                if ($meta = array_merge($meta, array_keys(array_filter([
                                    'in API' => $methodData->Api,
                                    'internal' => $methodData->Internal,
                                    'deprecated' => $methodData->Deprecated,
                                ])))) {
                                    $block[] = '<small>(' . implode(', ', $meta) . ')</small>';
                                    $block[] = '';
                                }

                                if ($methodData->Summary !== null) {
                                    $block[] = $methodData->Summary;
                                    $block[] = '';
                                }

                                $modifiers = implode(' ', $methodData->Modifiers);
                                $templates = $this->implodeTemplates($methodData->Templates, $eol);
                                $params = $this->implodeWithKeys(', ', $methodData->Parameters, true, '$');
                                $return = $methodData->ReturnType === null
                                    ? ''
                                    : ": {$methodData->ReturnType}";

                                if ($params === '' || (
                                    strpos($params . $return, "\n") === false
                                    && strlen(Arr::last(explode(
                                        "\n",
                                        $modifiers . $methodName . $templates . $params . $return,
                                    ))) < 80 - strlen($blockPrefix)
                                )) {
                                    $params = "({$params})";
                                } else {
                                    $params = "({$eol}    " . $this->implodeWithKeys(",{$eol}    ", $methodData->Parameters, true, '$') . "{$eol})";
                                }

                                $block[] = '```php';
                                $block[] = Arr::implode(' ', [
                                    $modifiers,
                                    'function',
                                    $methodName . $templates . $params . $return,
                                ], '');
                                $block[] = '```';

                                $printBlock();
                            }
                        }
                    }
                }
            }
        }

        Console::summary(Inflect::format($files, '{{#}} {{#:file}} analysed'), '', true);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,ConstantData|PropertyData|MethodData> $members
     * @return iterable<array<string,mixed>>
     */
    private function applyMemberData(array $row, string $type, array $members): iterable
    {
        $row['m_type'] = $type;
        foreach ($members as $name => $data) {
            $row['m_name'] = $name;
            $row['m_templates'] = $data instanceof MethodData
                ? $this->implodeWithKeys(', ', $data->Templates)
                : null;
            $row['m_summary'] = $data->Summary;
            $row['m_api'] = $data->Api;
            $row['m_internal'] = $data->Internal;
            $row['m_deprecated'] = $data->Deprecated;
            $row['m_declared'] = $data->Declared;
            $row['m_hasDocComment'] = $data->HasDocComment;
            $row['m_inherited'] = $data->Inherited;
            $row['m_inheritedFrom_class'] = $data->InheritedFrom[0] ?? null;
            $row['m_inheritedFrom_method'] = $data->InheritedFrom[1] ?? null;
            $row['m_prototype_class'] = $data->Prototype[0] ?? null;
            $row['m_prototype_method'] = $data->Prototype[1] ?? null;
            $row['m_abstract'] = $data->IsAbstract ?? false;
            $row['m_final'] = $data->IsFinal ?? false;
            $row['m_public'] = $data->IsPublic;
            $row['m_protected'] = $data->IsProtected;
            $row['m_private'] = $data->IsPrivate;
            $row['m_static'] = $data->IsStatic ?? false;
            $row['m_readonly'] = $data->IsReadOnly ?? false;
            $row['m_modifiers'] = implode(' ', $data->Modifiers);
            $row['m_parameters'] = $data instanceof MethodData
                ? $this->implodeWithKeys(', ', $data->Parameters, true, '$')
                : null;
            $row['m_type'] = $data->Type ?? $data->ReturnType ?? null;
            $row['m_value'] = $data->Value ?? $data->DefaultValue ?? null;
            $row['m_line'] = $data->Line;
            $row['m_lines'] = $data->Lines ?? null;

            foreach ($row as $key => $value) {
                if (is_bool($value)) {
                    $row[$key] = $value ? 'Y' : null;
                }
            }

            yield $row;
        }
    }

    /**
     * @param string[] $inherited
     */
    private function implodeInherited(string $keyword, array $inherited, string $eol): string
    {
        if (!$inherited) {
            return '';
        }

        return "{$keyword}{$eol}    " . implode(",{$eol}    ", $inherited);
    }

    /**
     * @param array<string,string> $templates
     */
    private function implodeTemplates(array $templates, string $eol): string
    {
        if (!$templates) {
            return '';
        }

        return count($templates) < 2
            ? '<' . $this->implodeWithKeys(', ', $templates) . '>'
            : "<{$eol}    " . $this->implodeWithKeys(",{$eol}    ", $templates) . "{$eol}>";
    }

    /**
     * @param array<string,array{string,string}|string> $array
     */
    private function implodeWithKeys(
        string $separator,
        array $array,
        bool $keyLast = false,
        string $keyPrefix = ''
    ): string {
        if (!$array) {
            return '';
        }

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                [$value, $suffix] = $value;
            } else {
                $suffix = '';
            }
            $result[] = $keyLast
                ? $value . $keyPrefix . $key . $suffix
                : $keyPrefix . $key . $value . $suffix;
        }

        return implode($separator, $result);
    }

    /**
     * @param array<string,ClassData> $data
     */
    private function sortClasses(array &$data): void
    {
        if (!$data) {
            return;
        }
        uksort(
            $data,
            fn($a, $b) =>
                strcasecmp($a, $b),
        );
        foreach ($data as $classData) {
            $this->sortMembers($classData->Constants);
            $this->sortMembers($classData->Properties);
            $this->sortMembers($classData->Methods);
        }
    }

    /**
     * @param array<string,ConstantData|PropertyData|MethodData> $data
     */
    private function sortMembers(array &$data): void
    {
        if (!$data) {
            return;
        }
        uasort(
            $data,
            fn($a, $b) =>
                ($a->Line ?? \PHP_INT_MAX) <=> ($b->Line ?? \PHP_INT_MAX)
                    ?: strcasecmp($a->Name, $b->Name),
        );
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function getClassData(ReflectionClass $class, TokenExtractor $extractor): ClassData
    {
        if (!$extractor->hasClass()) {
            // @codeCoverageIgnoreStart
            throw new ShouldNotHappenException('Extractor does not represent a class');
            // @codeCoverageIgnoreEnd
        }

        $className = $class->getName();
        $token = $extractor->getClassToken();
        $docBlocks = PHPDocUtil::getAllClassDocComments($class, true);
        $phpDoc = PHPDoc::fromDocBlocks($docBlocks);

        $data = new ClassData($className);

        $this->applyPhpDocData($phpDoc, $data);

        $start = $class->getStartLine();
        $end = $class->getEndLine();
        if ($start === false || $end === false) {
            // @codeCoverageIgnoreStart
            Console::warn(
                'Cannot get class start/end lines via reflection:',
                $className,
            );
            // @codeCoverageIgnoreEnd
        } else {
            $data->Line = $start;
            $data->Lines = $end - $start + 1;
        }

        if ($token->id === \T_CLASS) {
            $data->Modifiers = array_keys(array_filter([
                'abstract' => $data->IsAbstract = $class->isAbstract(),
                'final' => $data->IsFinal = $class->isFinal(),
                'readonly' => $data->IsReadOnly = \PHP_VERSION_ID >= 80200 && $class->isReadOnly(),
            ]));
            $data->IsFinal = $data->IsFinal || $phpDoc->hasTag('final');
            $data->IsReadOnly = $data->IsReadOnly || $phpDoc->hasTag('readonly');
        }

        if (
            ($token->id === \T_CLASS || $token->id === \T_INTERFACE)
            && ($next = $token->NextCode)
            && ($next = $next->NextCode)
        ) {
            $_extractor = $extractor->getParent();
            if ($next->id === \T_EXTENDS) {
                $data->Extends = $this->getNames($next, $_extractor);
            }
            if ($next->id === \T_IMPLEMENTS) {
                $data->Implements = $this->getNames($next, $_extractor);
            }
        }

        if ($token->id !== \T_INTERFACE) {
            $data->Uses = $class->getTraitNames();
        }

        foreach ($class->getMethods() as $method) {
            $functions ??= array_change_key_case(Get::array($extractor->getFunctions()));
            $name = $method->getName();
            $_name = Str::lower($name);
            $data->Methods[$name] = $this->getMethodData($method, $class, $functions[$_name] ?? null, $token);
        }

        foreach ($class->getProperties() as $property) {
            $properties ??= Get::array($extractor->getProperties());
            $name = $property->getName();
            $data->Properties[$name] = $this->getPropertyData($property, $class, $properties[$name] ?? null);
        }

        foreach ($class->getReflectionConstants() as $constant) {
            $constants ??= Get::array($extractor->getConstants());
            $name = $constant->getName();
            $data->Constants[$name] = $this->getConstantData($constant, $class, $constants[$name] ?? null);
        }

        return $data;
    }

    /**
     * @param-out NavigableToken $token
     * @return class-string[]
     */
    private function getNames(NavigableToken &$token, TokenExtractor $extractor): array
    {
        $names = [];
        while ($token->NextCode) {
            $token = $token->NextCode;
            $name = $extractor->getName($token, $token);
            if ($name !== null) {
                $name = (new ReflectionClass($name))->getName();
                $names[] = $name;
            }
            if ($token->id !== \T_COMMA) {
                break;
            }
        }

        return $names;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function getMethodData(
        ReflectionMethod $method,
        ReflectionClass $class,
        ?TokenExtractor $extractor,
        NavigableToken $classToken
    ): MethodData {
        $methodName = $method->getName();
        $docBlocks = PHPDocUtil::getAllMethodDocComments($method, $class, $classDocBlocks);
        $phpDoc = PHPDoc::fromDocBlocks($docBlocks, $classDocBlocks, "{$methodName}()");
        $declaring = $method->getDeclaringClass();
        $className = $class->getName();
        $declaringName = $declaring->getName();
        $declared = (bool) $extractor;
        $isInterface = $classToken->id === \T_INTERFACE;

        $data = new MethodData($methodName);
        $data->Declared = $declared;
        $data->Inherited = $declaringName !== $className;

        $this->applyPhpDocData($phpDoc, $data, $declared);

        if ($prototype = Reflect::getPrototype($method)) {
            $data->Prototype = [
                $prototype->getDeclaringClass()->getName(),
                $prototype->getName(),
            ];
        }

        if ($declared) {
            $start = $method->getStartLine();
            $end = $method->getEndLine();
            if ($start === false || $end === false) {
                // @codeCoverageIgnoreStart
                Console::warn(
                    'Cannot get method start/end lines via reflection:',
                    sprintf('%s::%s()', $className, $methodName),
                );
                // @codeCoverageIgnoreEnd
            } else {
                $data->Line = $start;
                $data->Lines = $end - $start + 1;
            }
        } elseif ($declaringName !== $className) {
            $data->InheritedFrom = [$declaringName, $methodName];
        } elseif ($inserted = Reflect::getTraitMethod($declaring, $methodName)) {
            $data->InheritedFrom = [
                $inserted->getDeclaringClass()->getName(),
                $inserted->getName(),
            ];
        }

        $data->Modifiers = array_keys(array_filter([
            'abstract' => $data->IsAbstract = !$isInterface && $method->isAbstract(),
            'final' => $data->IsFinal = $method->isFinal(),
            'public' => $data->IsPublic = $method->isPublic(),
            'protected' => $data->IsProtected = $method->isProtected(),
            'private' => $data->IsPrivate = $method->isPrivate(),
            'static' => $data->IsStatic = $method->isStatic(),
        ]));

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (
                ($tag = $phpDoc->getParams()[$name] ?? null)
                && ($tagType = $tag->getType()) !== null
            ) {
                $type = "{$tagType} ";
            } elseif ($param->hasType()) {
                $type = PHPDocUtil::getTypeDeclaration(
                    $param->getType(),
                    '\\',
                    fn($fqcn) => Get::basename($fqcn),
                ) . ' ';
            } else {
                $type = '';
            }
            if ($param->isPassedByReference()) {
                $type .= '&';
            }
            if ($param->isVariadic()) {
                $type .= '...';
            }
            $default = '';
            if ($param->isDefaultValueAvailable()) {
                $default .= ' = ';
                if ($param->isDefaultValueConstant()) {
                    $default .= $param->getDefaultValueConstantName();
                } else {
                    $default .= Get::code($param->getDefaultValue());
                }
            }
            $data->Parameters[$name] = [$type, $default];
        }

        if ($phpDoc->hasReturn()) {
            $data->ReturnType = $phpDoc->getReturn()->getType();
        } elseif ($method->hasReturnType()) {
            $data->ReturnType = PHPDocUtil::getTypeDeclaration(
                $method->getReturnType(),
                '\\',
                fn($fqcn) => Get::basename($fqcn),
            );
        }

        return $data;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function getPropertyData(
        ReflectionProperty $property,
        ReflectionClass $class,
        ?TokenExtractor $extractor
    ): PropertyData {
        $propertyName = $property->getName();
        $docBlocks = PHPDocUtil::getAllPropertyDocComments($property, $class, $classDocBlocks);
        $phpDoc = PHPDoc::fromDocBlocks($docBlocks, $classDocBlocks, "\${$propertyName}");
        $declaring = $property->getDeclaringClass();
        $className = $class->getName();
        $declaringName = $declaring->getName();
        $declared = (bool) $extractor;

        $data = new PropertyData($propertyName);
        $data->Declared = $declared;
        $data->Inherited = $declaringName !== $className;

        $this->applyPhpDocData($phpDoc, $data, $declared);

        if ($extractor) {
            /** @var NavigableToken */
            $token = $extractor->getMemberToken();
            $data->Line = $token->line;
        } elseif ($declaringName !== $className) {
            $data->InheritedFrom = [$declaringName, $propertyName];
        } elseif ($inserted = Reflect::getTraitProperty($declaring, $propertyName)) {
            $data->InheritedFrom = [
                $inserted->getDeclaringClass()->getName(),
                $inserted->getName(),
            ];
        }

        $data->Modifiers = array_keys(array_filter([
            'public' => $data->IsPublic = $property->isPublic(),
            'protected' => $data->IsProtected = $property->isProtected(),
            'private' => $data->IsPrivate = $property->isPrivate(),
            'static' => $data->IsStatic = $property->isStatic(),
            'readonly' => $data->IsReadOnly = \PHP_VERSION_ID >= 80100 && $property->isReadOnly(),
        ]));

        $vars = $phpDoc->getVars();
        if (count($vars) === 1 && array_key_first($vars) === 0) {
            $data->Type = $vars[0]->getType();
        } elseif ($property->hasType()) {
            $data->Type = PHPDocUtil::getTypeDeclaration(
                $property->getType(),
                '\\',
                fn($fqcn) => Get::basename($fqcn),
            );
        }

        if ($property->hasDefaultValue() && (
            ($value = $property->getDefaultValue()) !== null
            || $property->hasType()
        )) {
            if (mb_strlen($code = Get::code($value)) > 20) {
                if ($declared) {
                    if (is_array($value)) {
                        $code = Get::code($value, ",\n");
                    }
                } elseif (is_array($value) || is_string($value)) {
                    $code = '<' . Get::type($value) . '>';
                }
            }
            $data->DefaultValue = $code;
        }

        return $data;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function getConstantData(
        ReflectionClassConstant $constant,
        ReflectionClass $class,
        ?TokenExtractor $extractor
    ): ConstantData {
        $constantName = $constant->getName();
        $docBlocks = PHPDocUtil::getAllConstantDocComments($constant, $class, $classDocBlocks);
        $phpDoc = PHPDoc::fromDocBlocks($docBlocks, $classDocBlocks, $constantName);
        $declaring = $constant->getDeclaringClass();
        $className = $class->getName();
        $declaringName = $declaring->getName();
        $declared = (bool) $extractor;

        $data = new ConstantData($constantName);
        $data->Declared = $declared;
        $data->Inherited = $declaringName !== $className;

        $this->applyPhpDocData($phpDoc, $data, $declared);

        if ($extractor) {
            /** @var NavigableToken */
            $token = $extractor->getMemberToken();
            $data->Line = $token->line;
        } elseif ($declaringName !== $className) {
            $data->InheritedFrom = [$declaringName, $constantName];
        } elseif ($inserted = Reflect::getTraitConstant($declaring, $constantName)) {
            $data->InheritedFrom = [
                $inserted->getDeclaringClass()->getName(),
                $inserted->getName(),
            ];
        }

        $data->Modifiers = array_keys(array_filter([
            'final' => $data->IsFinal = \PHP_VERSION_ID >= 80100 && $constant->isFinal(),
            'public' => $data->IsPublic = $constant->isPublic(),
            'protected' => $data->IsProtected = $constant->isProtected(),
            'private' => $data->IsPrivate = $constant->isPrivate(),
        ]));

        $vars = $phpDoc->getVars();
        if (count($vars) === 1 && array_key_first($vars) === 0) {
            $data->Type = $vars[0]->getType();
        }

        $value = $constant->getValue();
        if (mb_strlen($code = Get::code($value)) > 20) {
            if ($declared) {
                if (is_array($value)) {
                    $code = Get::code($value, ",\n");
                }
            } elseif (is_array($value) || is_string($value)) {
                $code = '<' . Get::type($value) . '>';
            }
        }
        $data->Value = $code;

        return $data;
    }

    /**
     * @param ClassData|ConstantData|PropertyData|MethodData $data
     */
    private function applyPhpDocData(PHPDoc $phpDoc, $data, bool $declared = true): void
    {
        $data->Summary = $phpDoc->getSummary();
        $data->Api = $declared && $phpDoc->hasTag('api');
        $data->Internal = $declared && $phpDoc->hasTag('internal');
        $data->Deprecated = $phpDoc->hasTag('deprecated');
        $data->HasDocComment = $declared && !$phpDoc->isEmpty();

        if (!$data instanceof ClassData && !$data instanceof MethodData) {
            return;
        }

        foreach ($phpDoc->getTemplates(false) as $name => $tag) {
            $template = '';
            if (($type = $tag->getType()) !== null) {
                $template .= " of {$type}";
            }
            if (($default = $tag->getDefault()) !== null) {
                $template .= " = {$default}";
            }
            $data->Templates[$name] = $template;
        }
    }
}
