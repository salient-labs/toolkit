<?php declare(strict_types=1);

namespace Salient\Sli\Command\Analyse;

use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Contract\Cli\CliOptionVisibility;
use Salient\Core\Facade\Console;
use Salient\PHPDoc\PHPDoc;
use Salient\PHPDoc\PHPDocUtil;
use Salient\Sli\Command\AbstractCommand;
use Salient\Sli\Internal\NavigableToken;
use Salient\Sli\Internal\TokenExtractor;
use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Json;
use Salient\Utility\Reflect;
use Salient\Utility\Str;
use ReflectionClass;
use ReflectionMethod;

class AnalyseClass extends AbstractCommand
{
    /** @var string[] */
    private array $Path = [];
    private string $Format = '';
    private string $Exclude = '';
    private ?string $Autoload = null;
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
                ->name('format')
                ->description('Output format')
                ->optionType(CliOptionType::ONE_OF_POSITIONAL)
                ->allowedValues(['json', 'csv', 'md'])
                ->required()
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

        if ($this->Autoload !== null) {
            require_once $this->Autoload;
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

        Console::info(Inflect::format($files, 'Analysing {{#}} {{#:file}}'));

        $tree = [];
        $data = [];

        $loaded = [];
        $current = [
            'file' => null,
            'line' => null,
            'type' => null,
            'name' => null,
            'namespace' => null,
            'shortName' => null,
        ];
        foreach ($files as $file) {
            $current['file'] = $file;
            $extractors = TokenExtractor::fromFile($file)->getNamespaces();
            foreach ($extractors as $namespace => $extractor) {
                $current['namespace'] = $namespace;
                $delimiter = $namespace === '' ? '' : '\\';
                foreach ([
                    'class' => 'getClasses',
                    'interface' => 'getInterfaces',
                    'trait' => 'getTraits',
                    'enum' => 'getEnums',
                ] as $type => $extractorMethod) {
                    $current['type'] = $type;
                    $existsFunction = $type . '_exists';
                    /** @var iterable<string,TokenExtractor> */
                    $classes = $extractor->$extractorMethod();
                    foreach ($classes as $class => $extractor) {
                        /** @var NavigableToken */
                        $token = $extractor->getClassToken();
                        /** @var class-string */
                        $fqcn = $namespace . $delimiter . $class;
                        $_fqcn = Get::fqcn($fqcn);
                        if (isset($loaded[$_fqcn])) {
                            continue;
                        }
                        if (!$existsFunction($fqcn)) {
                            Console::warn(
                                sprintf('Cannot load %s:', $type),
                                sprintf('%s (in %s:%d)', $fqcn, $file, $token->line)
                            );
                            continue;
                        }
                        $loaded[$_fqcn] = true;
                        $current['line'] = $token->line;
                        $current['name'] = $fqcn;
                        $current['shortName'] = $class;

                        $classData = $this->getClassData($fqcn, $extractor);

                        $tree[$namespace][$class] = [
                            'type' => $type,
                        ] + $classData + [
                            'name' => $fqcn,
                            'file' => $file,
                            'line' => $token->line,
                        ];

                        $methods = $classData['methods'];
                        unset($classData['methods']);

                        $_current = array_merge($current + $classData, [
                            'extends' => implode(', ', $classData['extends']),
                            'implements' => implode(', ', $classData['implements']),
                            'modifiers' => implode(' ', $classData['modifiers']),
                        ]);

                        foreach ($this->applyMemberData($_current, 'method', $methods) as $method) {
                            $data[] = $method;
                        }

                        if ($this->Debug) {
                            Console::debug(sprintf('Loaded %s:', $type), $fqcn);
                        }
                    }
                }
            }
        }

        $stdout = Console::getStdoutTarget();
        $tty = $stdout->isTty();

        switch ($this->Format) {
            case 'json':
                $eol = $tty ? $stdout->getEol() : \PHP_EOL;
                echo Json::prettyPrint($tree, 0, $eol) . $eol;
                break;

            case 'csv':
                $eol = $tty ? $stdout->getEol() : "\r\n";
                File::writeCsv('php://output', $data, true, null, null, $count, $eol, !$tty, !$tty);
                break;

            case 'md':
                break;
        }

        Console::summary(Inflect::format($files, '{{#}} {{#:file}} analysed'), '', true);
    }

    /**
     * @param array{file:string,line:int|null,type:string,name:class-string,namespace:string,shortName:string,summary:string|null,extends:string,implements:string,api:bool,internal:bool,abstract:bool,final:bool,readonly?:bool,modifiers:string} $current
     * @param array<string,array{summary:string|null,api:bool,internal:bool,declared:bool,inherited:bool,prototype:array{class-string,string}|null,line:int|null,lines:int|null,abstract:bool,final:bool,public:bool,protected:bool,private:bool,static:bool,modifiers:string[]}> $members
     * @return iterable<array{file:string,line:int|null,type:string,name:class-string,namespace:string,shortName:string,summary:string|null,extends:string,implements:string,api:bool,internal:bool,abstract:bool,final:bool,readonly?:bool,modifiers:string,m_type:string,m_name:string,m_summary:string|null,m_api:bool,m_internal:bool,m_declared:bool,m_inherited:bool,m_prototype_class:class-string|null,m_prototype_method:string|null,m_line:int|null,m_lines:int|null,m_abstract:bool,m_final:bool,m_public:bool,m_protected:bool,m_private:bool,m_static:bool,m_modifiers:string}>
     */
    private function applyMemberData(array $current, string $type, array $members): iterable
    {
        $current['m_type'] = $type;
        foreach ($members as $name => $data) {
            $current['m_name'] = $name;
            $current['m_summary'] = $data['summary'];
            $current['m_api'] = $data['api'];
            $current['m_internal'] = $data['internal'];
            $current['m_declared'] = $data['declared'];
            $current['m_inherited'] = $data['inherited'];
            $current['m_prototype_class'] = $data['prototype'][0] ?? null;
            $current['m_prototype_method'] = $data['prototype'][1] ?? null;
            $current['m_line'] = $data['line'];
            $current['m_lines'] = $data['lines'];
            $current['m_abstract'] = $data['abstract'];
            $current['m_final'] = $data['final'];
            $current['m_public'] = $data['public'];
            $current['m_protected'] = $data['protected'];
            $current['m_private'] = $data['private'];
            $current['m_static'] = $data['static'];
            $current['m_modifiers'] = implode(' ', $data['modifiers']);
            yield $current;
        }
    }

    /**
     * @param class-string $class
     * @return array{summary:string|null,extends:class-string[],implements:class-string[],api:bool,internal:bool,abstract:bool,final:bool,readonly?:bool,modifiers:string[],methods:array<string,array{summary:string|null,api:bool,internal:bool,declared:bool,inherited:bool,prototype:array{class-string,string}|null,line:int|null,lines:int|null,abstract:bool,final:bool,public:bool,protected:bool,private:bool,static:bool,modifiers:string[]}>}
     */
    private function getClassData(string $class, TokenExtractor $extractor): array
    {
        if (!$extractor->hasClass()) {
            // @codeCoverageIgnoreStart
            throw new ShouldNotHappenException('Extractor does not represent a class');
            // @codeCoverageIgnoreEnd
        }

        $token = $extractor->getClassToken();
        $class = new ReflectionClass($class);
        $docBlocks = PHPDocUtil::getAllClassDocComments($class);
        $phpDoc = PHPDoc::fromDocBlocks($docBlocks);

        $data = [
            'summary' => null,
            'extends' => [],
            'implements' => [],
            'api' => false,
            'internal' => false,
        ];

        if ($phpDoc) {
            $data['summary'] = $phpDoc->getSummary();
            $data['api'] = isset($phpDoc->getTagsByName()['api']);
            $data['internal'] = isset($phpDoc->getTagsByName()['internal']);
        }

        $modifiers = [
            'abstract' => $token->id === \T_CLASS && $class->isAbstract(),
            'final' => $token->id === \T_CLASS && $class->isFinal(),
        ];
        if (\PHP_VERSION_ID >= 80200) {
            $modifiers['readonly'] = $token->id === \T_CLASS && $class->isReadOnly();
        }

        if (
            ($token->id === \T_CLASS || $token->id === \T_INTERFACE)
            && ($next = $token->NextCode)
            && ($next = $next->NextCode)
        ) {
            $_extractor = $extractor->getParent();
            if ($next->id === \T_EXTENDS) {
                $data['extends'] = $this->getNames($next, $_extractor);
            }
            if ($next->id === \T_IMPLEMENTS) {
                $data['implements'] = $this->getNames($next, $_extractor);
            }
        }

        $data += $modifiers + [
            'modifiers' => array_keys(array_filter($modifiers)),
        ];

        $data['methods'] = [];
        foreach ($class->getMethods() as $method) {
            $functions ??= array_change_key_case(Get::array($extractor->getFunctions()));
            $name = $method->getName();
            $_name = Str::lower($name);
            $data['methods'][$name] = $this->getMethodData($method, $class, $functions[$_name] ?? null);
        }

        foreach ($class->getProperties() as $property) {
        }

        foreach ($class->getReflectionConstants() as $constant) {
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
     * @return array{summary:string|null,api:bool,internal:bool,declared:bool,inherited:bool,prototype:array{class-string,string}|null,line:int|null,lines:int|null,abstract:bool,final:bool,public:bool,protected:bool,private:bool,static:bool,modifiers:string[]}
     */
    private function getMethodData(ReflectionMethod $method, ReflectionClass $class, ?TokenExtractor $extractor): array
    {
        $docBlocks = PHPDocUtil::getAllMethodDocComments($method, null, $classDocBlocks);
        $phpDoc = PHPDoc::fromDocBlocks($docBlocks, $classDocBlocks);
        $declaring = $method->getDeclaringClass();
        $className = $class->getName();
        $declaringName = $declaring->getName();
        $declared = (bool) $extractor;

        $data = [
            'summary' => null,
            'api' => false,
            'internal' => false,
            'declared' => $declared,
            'inherited' => $declaringName !== $className,
            'prototype' => null,
            'line' => null,
            'lines' => null,
        ];

        if ($prototype = Reflect::getPrototype($method)) {
            $data['prototype'] = [
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
                    sprintf('%s::%s()', $className, $method->getName()),
                );
                // @codeCoverageIgnoreEnd
            } else {
                $data['line'] = $start;
                $data['lines'] = $end - $start + 1;
            }
        }

        if ($phpDoc) {
            $data['summary'] = $phpDoc->getSummary();
            $data['api'] = isset($phpDoc->getTagsByName()['api']);
            $data['internal'] = isset($phpDoc->getTagsByName()['internal']);
        }

        $modifiers = [
            'abstract' => $method->isAbstract(),
            'final' => $method->isFinal(),
            'public' => $method->isPublic(),
            'protected' => $method->isProtected(),
            'private' => $method->isPrivate(),
            'static' => $method->isStatic(),
        ];

        $data += $modifiers + [
            'modifiers' => array_keys(array_filter($modifiers)),
        ];

        return $data;
    }
}
