<?php declare(strict_types=1);

namespace Salient\Sli\Command;

use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Contract\Cli\CliOptionVisibility;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Profile;
use Salient\PHPDoc\PHPDocUtil;
use Salient\Sli\Internal\Data\ClassData;
use Salient\Sli\Internal\Data\ClassDataFactory;
use Salient\Sli\Internal\Data\ConstantData;
use Salient\Sli\Internal\Data\MethodData;
use Salient\Sli\Internal\Data\NamespaceData;
use Salient\Sli\Internal\Data\PropertyData;
use Salient\Sli\Internal\TokenExtractor;
use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\Arr;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Json;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use ReflectionClass;

class AnalyseClass extends AbstractCommand implements ClassDataFactory
{
    private const SIMPLE_TYPE = '/^\??' . Regex::PHP_IDENTIFIER . '(?:\[\])?(?:\|null)?$/iD';

    /** @var string[] */
    private array $Path = [];
    private ?string $Json = null;
    private ?string $Csv = null;
    private ?string $Markdown = null;
    /** @var string[] */
    private array $Skip = [];
    /** @var class-string[] */
    private array $SkipFrom = [];
    private string $Exclude = '';
    /** @var string[] */
    private array $Autoload = [];
    private bool $Debug = false;

    // --

    /**
     * @var array<string,bool>
     */
    private array $SkipIndex = [
        'internal' => false,
        'private' => false,
        'inherited' => false,
        // Markdown-specific
        'desc' => false,
        'from' => false,
        'lines' => false,
        'meta' => false,
        'star' => false,
    ];

    /** @var array<class-string,true> */
    private array $SkipFromIndex;
    /** @var array<class-string,ClassData> */
    private array $Index;
    /** @var array<string,NamespaceData> */
    private array $Data;
    private string $Tab = '    ';
    private string $Eol;
    private bool $OutputIsTty;
    private bool $StdoutIsTty;
    private string $StdoutEol;

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
                ->long('json')
                ->short('j')
                ->valueName('file')
                ->description('Generate JSON output')
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->valueType(CliOptionValueType::NEW_FILE_OR_DASH)
                ->defaultValue('-')
                ->bindTo($this->Json),
            CliOption::build()
                ->long('csv')
                ->short('c')
                ->valueName('file')
                ->description('Generate CSV output')
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->valueType(CliOptionValueType::NEW_FILE_OR_DASH)
                ->defaultValue('-')
                ->bindTo($this->Csv),
            CliOption::build()
                ->long('markdown')
                ->short('m')
                ->valueName('file')
                ->description('Generate Markdown output')
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->valueType(CliOptionValueType::NEW_FILE_OR_DASH)
                ->defaultValue('-')
                ->bindTo($this->Markdown),
            CliOption::build()
                ->long('skip')
                ->short('k')
                ->description('Exclude items from the output')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys($this->SkipIndex))
                ->multipleAllowed()
                ->bindTo($this->Skip),
            CliOption::build()
                ->long('skip-from')
                ->short('K')
                ->valueName('class')
                ->description('Exclude members inherited from a class')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->SkipFrom),
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

        $this->SkipIndex = array_merge(
            array_fill_keys(array_keys($this->SkipIndex), false),
            array_fill_keys($this->Skip, true),
        );

        $this->SkipFromIndex = array_fill_keys(
            array_map([Get::class, 'fqcn'], $this->SkipFrom),
            true,
        );

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

        $this->Index = [];
        $this->Data = [];
        foreach ($files as $file) {
            $this->loadFile($file, true);
        }

        uksort($this->Data, 'strcasecmp');
        foreach ($this->Data as $nsData) {
            $nsData->sort($this);
        }

        if ($this->Json !== null) {
            $file = $this->getOutputFile($this->Json);
            File::writeContents(
                $file,
                Json::prettyPrint($this->Data, 0, $this->Eol) . $this->Eol,
            );
        }

        if ($this->Csv !== null) {
            $file = $this->getOutputFile($this->Csv, "\r\n");

            $data = [];
            foreach ($this->Data as $ns => $nsData) {
                $delimiter = $ns === '' ? '' : '\\';
                foreach (NamespaceData::TYPE as $type => [$nsProperty]) {
                    /** @var array<string,ClassData> */
                    $classes = $nsData->$nsProperty;
                    foreach ($classes as $class => $classData) {
                        $fqcn = $ns . $delimiter . $class;
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
                                'summaryInherited',
                                'descriptionInherited',
                                'constants',
                                'properties',
                                'methods',
                            ),
                            [
                                'summary' => $classData->SummaryInherited ? null : $classData->Summary,
                                'description' => $classData->DescriptionInherited ? null : $classData->Description,
                                'templates' => $this->implodeWithKeys(', ', $classData->Templates),
                                'extends' => implode(', ', $classData->Extends),
                                'implements' => implode(', ', $classData->Implements),
                                'uses' => implode(', ', $classData->Uses),
                                'modifiers' => implode(' ', $classData->Modifiers),
                            ],
                        );

                        foreach (ClassData::TYPE as $memberType => [$classProperty]) {
                            /** @var array<string,ConstantData|PropertyData|MethodData> */
                            $members = $classData->$classProperty;
                            foreach ($this->applyMemberData(
                                $row,
                                $memberType,
                                $members,
                                $this->Eol,
                            ) as $memberRow) {
                                $data[] = $memberRow;
                            }
                        }
                    }
                }
            }

            File::writeCsv(
                $file,
                $data,
                true,
                null,
                null,
                $count,
                $this->Eol,
                !$this->OutputIsTty,
                !$this->OutputIsTty,
            );
        }

        if ($this->Markdown !== null) {
            $file = $this->getOutputFile($this->Markdown);
            $eol = $this->Eol;
            $stream = File::open($file, 'w');

            /** @var string[] */
            $block = [];
            $blockPrefix = '';
            $lastPrefix = null;
            $printBlock = function (
                ?string $line = null,
                bool $continue = false
            ) use (
                $eol,
                $stream,
                &$block,
                &$blockPrefix,
                &$lastPrefix
            ) {
                if ($line !== null) {
                    $block[] = $line;
                }
                if (!$block) {
                    $blockPrefix = '';
                }
                $trimmed = rtrim($blockPrefix);
                if ($lastPrefix !== null) {
                    // @phpstan-ignore identical.alwaysTrue
                    if ($lastPrefix === $blockPrefix) {
                        File::writeAll($stream, $eol . $trimmed . $eol);
                    } else {
                        File::writeAll($stream, $eol . $eol);
                    }
                    $lastPrefix = null;
                }
                if ($block) {
                    $block = implode("\n", $block);
                    $replace = $eol . $blockPrefix;
                    if ($replace !== "\n") {
                        $block = $blockPrefix . str_replace("\n", $replace, $block);
                    }
                    if ($trimmed !== $blockPrefix) {
                        $regex = Regex::quote($blockPrefix, '/');
                        $block = Regex::replace("/(?<=\n|^){$regex}(?=\n|\$)/D", $trimmed, $block);
                    }
                    if (!$continue) {
                        File::writeAll($stream, $block . $eol . $eol);
                    } else {
                        File::writeAll($stream, $block);
                        $lastPrefix = $blockPrefix;
                    }
                    $block = [];
                }
                if (!$continue) {
                    $blockPrefix = '';
                }
            };

            $noDesc = $this->SkipIndex['desc'];
            $noFrom = $this->SkipIndex['from'];
            $noLines = $this->SkipIndex['lines'];
            $noMeta = $this->SkipIndex['meta'];

            foreach ($this->Data as $ns => $nsData) {
                if ($ns === '') {
                    $printBlock('## Global space');
                } else {
                    $printBlock("## `{$ns}`");
                }

                foreach (NamespaceData::TYPE as $type => [
                    $nsProperty,
                    $typeHeading,
                ]) {
                    /** @var array<string,ClassData> */
                    $classes = $nsData->$nsProperty;
                    if (!$classes) {
                        continue;
                    }

                    foreach ($classes as $class => $classData) {
                        $printBlock("### {$typeHeading} `{$class}`");

                        $meta = [];
                        if (!$noMeta && !$noLines && $classData->Lines !== null) {
                            $meta[] = Inflect::format($classData->Lines, '{{#}} {{#:line}}');
                        }

                        if ($meta = array_merge($meta, $noMeta ? [] : array_keys(array_filter([
                            'no DocBlock' => !$classData->HasDocComment,
                            'in API' => $classData->Api,
                            'internal' => $classData->Internal,
                            'deprecated' => $classData->Deprecated,
                        ])))) {
                            $printBlock('<small>(' . implode(', ', $meta) . ')</small>');
                        }

                        if ($classData->Summary !== null) {
                            $printBlock(Str::escapeMarkdown($classData->Summary));
                            if (!$noDesc && $classData->Description !== null && !$classData->DescriptionInherited) {
                                $printBlock($classData->Description);
                            }
                        }

                        if (
                            $classData->Modifiers
                            || $classData->Templates
                            || $classData->Extends
                            || $classData->Implements
                            || $classData->Uses
                        ) {
                            $block[] = '```php';
                            $block[] = Arr::implode(' ', [
                                implode(' ', $classData->Modifiers),
                                $type,
                                $class . $this->implodeTemplates($classData->Templates),
                            ], '');
                            foreach ([
                                'extends' => $classData->Extends,
                                'implements' => $classData->Implements,
                                'uses' => $classData->Uses,
                            ] as $keyword => $inherited) {
                                if (!$inherited) {
                                    continue;
                                }
                                $names = [];
                                foreach ($inherited as $name) {
                                    $names[] = Get::basename($name);
                                }
                                $block[] = "{$keyword} " . implode(', ', $names);
                            }
                            $printBlock('```');
                        }

                        foreach ([
                            'Constants' => [
                                $classData->Constants,
                                ['Type'],
                                fn(ConstantData $data) => $data->getStructuralElementName(),
                                fn(ConstantData $data) => Arr::implode(' ', [
                                    implode(' ', $data->Modifiers),
                                    $data->Type,
                                    "{$data->Name} = {$data->Value}",
                                ], ''),
                                fn(ConstantData $data) => Arr::implode(' ', [
                                    implode(' ', $data->Modifiers),
                                    'const',
                                    $data->Type,
                                    "{$data->Name} = {$data->Value}",
                                ], ''),
                                fn(ConstantData $data) => [],
                            ],
                            'Properties' => [
                                $classData->Properties,
                                ['Type'],
                                fn(PropertyData $data) => $data->getStructuralElementName(),
                                null,
                                fn(PropertyData $data) => Arr::implode(' ', [
                                    implode(' ', $data->Modifiers),
                                    $data->Type,
                                    "\${$data->Name}" . (
                                        $data->DefaultValue === null
                                            ? ''
                                            : " = {$data->DefaultValue}"
                                    ),
                                ], ''),
                                fn(PropertyData $data) => [],
                            ],
                            'Methods' => [
                                $classData->Methods,
                                ['Templates' => [], 'ReturnType'],
                                fn(MethodData $data) => $data->getStructuralElementName(),
                                fn(MethodData $data) => $this->getMethodParts($data, true),
                                fn(MethodData $data) => $this->getMethodParts($data),
                                function (MethodData $data) use ($noLines, $noMeta) {
                                    if ($noMeta || $data->InheritedFrom) {
                                        return [];
                                    }
                                    if ($prototype = $data->Prototype) {
                                        $verb = interface_exists($prototype[0]) ? 'implements' : 'overrides';
                                        $prototype[0] = Get::basename($prototype[0]);
                                        $meta[] = sprintf('%s `%s::%s()`', $verb, ...$prototype);
                                    }
                                    if (!$noLines && $data->Class->Type !== 'interface' && $data->Lines !== null) {
                                        $meta[] = Inflect::format($data->Lines, '{{#}} {{#:line}}');
                                    }
                                    if (!$data->HasDocComment && !$data->Magic) {
                                        $meta[] = 'no DocBlock';
                                    }
                                    return $meta ?? [];
                                }
                            ],
                        ] as $membersHeading => [
                            $members,
                            $collapsible,
                            $headingCallback,
                            $collapsedCallback,
                            $declarationCallback,
                            $metaCallback,
                        ]) {
                            $members = ClassData::filterMembers($members);
                            if (!$members) {
                                continue;
                            }

                            $collapsedCallback ??= $declarationCallback;

                            $printBlock("#### {$membersHeading}");

                            $collapsedHeadings = [];
                            $expected = null;
                            foreach ($members as $memberName => $memberData) {
                                $from = $memberData->InheritedFrom[0] ?? '';
                                if (($collapsedHeadings[$from] ?? null) === []) {
                                    continue;
                                }
                                $collapsed = $collapsedCallback($memberData);
                                if (
                                    strpos($collapsed, "\n") !== false
                                    || mb_strlen($collapsed) > 69
                                ) {
                                    $collapsedHeadings[$from] = [];
                                    continue;
                                }
                                foreach ($collapsible as $key => $memberProperty) {
                                    $strict = false;
                                    if (is_string($key)) {
                                        $expected = $memberProperty;
                                        $memberProperty = $key;
                                        $strict = true;
                                    }
                                    $value = $memberData->$memberProperty;
                                    if (!(
                                        $value === null || (
                                            $strict && $value === $expected
                                        ) || (
                                            !$strict
                                            && is_string($value)
                                            && Regex::match(self::SIMPLE_TYPE, $value)
                                        )
                                    )) {
                                        $collapsedHeadings[$from] = [];
                                        continue 2;
                                    }
                                }
                                $collapsedHeadings[$from][$memberName] = $collapsed;
                            }

                            $lastFrom = '';
                            $headingTag = '#####';
                            foreach ($members as $memberName => $memberData) {
                                $from = $memberData->InheritedFrom[0] ?? '';
                                if ($from !== $lastFrom) {
                                    if ($from !== '') {
                                        if (!$noFrom) {
                                            $blockPrefix = '';
                                            $fromBasename = Get::basename($from);
                                            $printBlock("##### Inherited from `{$fromBasename}`");
                                        }
                                        $blockPrefix = '> ';
                                        $headingTag = '######';
                                    } else {
                                        // In case declared members aren't first
                                        $blockPrefix = '';
                                        $headingTag = '#####';
                                    }
                                    $lastFrom = $from;
                                }

                                $collapsed = $collapsedHeadings[$from][$memberName] ?? null;
                                $heading = $collapsed ?? $headingCallback($memberData);
                                $tag = $memberData instanceof PropertyData
                                    && $memberData->IsWriteOnly ? ' (write-only)' : '';
                                $tag .= !$this->SkipIndex['star']
                                    && ($memberData instanceof PropertyData || $memberData instanceof MethodData)
                                    && $memberData->Magic ? ' ★' : '';

                                $printBlock("{$headingTag} `{$heading}`{$tag}", true);

                                $meta = $metaCallback($memberData);
                                if ($meta = array_merge($meta, $noMeta ? [] : array_keys(array_filter([
                                    'in API' => $memberData->Api,
                                    'internal' => $memberData->Internal,
                                    'deprecated' => $memberData->Deprecated,
                                ])))) {
                                    $printBlock('<small>(' . implode(', ', $meta) . ')</small>', true);
                                }

                                if ($memberData->Summary !== null) {
                                    $printBlock(Str::escapeMarkdown($memberData->Summary), true);
                                    if (!$noDesc && $memberData->Description !== null && !$memberData->DescriptionInherited) {
                                        $printBlock($memberData->Description);
                                    }
                                }

                                if ($collapsed === null) {
                                    $block[] = '```php';
                                    $block[] = $declarationCallback($memberData);
                                    $printBlock('```', true);
                                }
                            }
                            $printBlock();
                        }
                    }
                }
            }

            File::close($stream);
        }

        foreach (Arr::sortDesc(Profile::getInstance()->getCounters('type'), true) as $type => $count) {
            Console::log(Str::upperFirst(Inflect::plural($type)) . ':', (string) $count);
        }

        Console::summary(Inflect::format($files, '{{#}} {{#:file}} analysed'), '', true);
    }

    private function getOutputFile(string $file, string $defaultEol = \PHP_EOL): string
    {
        if ($file === '-') {
            if (!isset($this->StdoutIsTty)) {
                $stdout = Console::getStdoutTarget();
                if ($this->StdoutIsTty = $stdout->isTty()) {
                    $this->StdoutEol = $stdout->getEol();
                }
            }

            $this->Eol = $this->StdoutIsTty
                ? $this->StdoutEol
                : $defaultEol;
            $this->OutputIsTty = $this->StdoutIsTty;

            return 'php://output';
        }

        File::createDir(dirname($file));

        $this->Eol = $defaultEol;
        $this->OutputIsTty = false;

        return $file;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,ConstantData|PropertyData|MethodData> $members
     * @return iterable<array<string,mixed>>
     */
    private function applyMemberData(array $row, string $type, array $members, string $eol): iterable
    {
        $row['m_type'] = $type;
        foreach (ClassData::filterMembers($members) as $name => $data) {
            $row['m_name'] = $name;
            $row['m_templates'] = $data instanceof MethodData
                ? $this->implodeWithKeys(', ', $data->Templates)
                : null;
            $row['m_summary'] = $data->SummaryInherited ? null : $data->Summary;
            $row['m_description'] = $data->DescriptionInherited ? null : $data->Description;
            $row['m_api'] = $data->Api;
            $row['m_internal'] = $data->Internal;
            $row['m_deprecated'] = $data->Deprecated;
            $row['m_magic'] = $magic = $data->Magic ?? false;
            $row['m_declared'] = $data->Declared;
            $row['m_hasDocComment'] = $data->HasDocComment;
            $row['m_needsDocComment'] = !$data->InheritedFrom && !$data->HasDocComment && !$magic;
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
            $row['m_writeonly'] = $data->IsWriteOnly ?? false;
            $row['m_modifiers'] = implode(' ', $data->Modifiers);
            $row['m_parameters'] = $data instanceof MethodData
                ? $this->implodeWithKeys(', ', $data->Parameters, true, '$')
                : null;
            $row['m_return_type'] = $data->Type ?? $data->ReturnType ?? null;
            $row['m_value'] = $data->Value ?? $data->DefaultValue ?? null;
            $row['m_line'] = $data->Line;
            $row['m_lines'] = $data->Lines ?? null;

            foreach ($row as $key => $value) {
                if (is_bool($value)) {
                    $row[$key] = $value ? 'Y' : null;
                } elseif (
                    is_string($value)
                    && $eol !== "\n"
                    && strpos($value, "\n") !== false
                ) {
                    $row[$key] = str_replace("\n", $eol, $value);
                }
            }

            yield $row;
        }
    }

    private function getMethodParts(MethodData $data, bool $collapsed = false): string
    {
        $name = $data->Name;
        $modifiers = implode(' ', $data->Modifiers);
        $templates = $this->implodeTemplates($data->Templates);
        $params = $this->implodeWithKeys(', ', $data->Parameters, true, '$');
        $return = $data->ReturnType === null
            ? ''
            : ": {$data->ReturnType}";

        if ($params === '' || (
            strpos($params . $return, "\n") === false
            && strlen(Arr::last(explode("\n", Arr::implode(' ', [
                $modifiers,
                'function',
                $name . $templates . $params . $return,
            ])))) <= 76
        )) {
            $params = "({$params})";
        } else {
            $params = "(\n{$this->Tab}" . $this->implodeWithKeys(",\n{$this->Tab}", $data->Parameters, true, '$') . "\n)";
        }

        return $collapsed
            ? Arr::implode(' ', [
                $modifiers,
                $name . $templates . $params . $return,
            ], '')
            : Arr::implode(' ', [
                $modifiers,
                'function',
                $name . $templates . $params . $return,
            ], '');
    }

    /**
     * @param array<string,string> $templates
     */
    private function implodeTemplates(array $templates): string
    {
        if (!$templates) {
            return '';
        }

        return count($templates) < 2
            ? '<' . $this->implodeWithKeys(', ', $templates) . '>'
            : "<\n{$this->Tab}" . $this->implodeWithKeys(",\n{$this->Tab}", $templates) . "\n>";
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

        return PHPDocUtil::removeTypeNamespaces(implode($separator, $result));
    }

    /**
     * @inheritDoc
     */
    public function getClassData(string $class): ClassData
    {
        $_fqcn = Get::fqcn($fqcn = $class);
        if (isset($this->Index[$_fqcn])) {
            return $this->Index[$_fqcn];
        }

        $class = new ReflectionClass($fqcn);
        $file = $class->getFileName();
        if ($file !== false) {
            $this->loadFile($file);
            if (!isset($this->Index[$_fqcn])) {
                throw new ShouldNotHappenException(sprintf(
                    '%s not loaded from %s',
                    $fqcn,
                    $file,
                ));
            }
        } else {
            $this->Index[$_fqcn] = ClassData::fromReflection(
                $class,
                null,
                Console::getInstance(),
            );
            Console::debug('Loaded (internal):', $fqcn);
        }

        return $this->Index[$_fqcn];
    }

    private function loadFile(string $filename, bool $collect = false): void
    {
        $extractor = TokenExtractor::fromFile($filename, "\n");
        NamespaceData::fromExtractor(
            $extractor,
            function ($data, $type) use ($collect) {
                if ($this->Debug && $data instanceof ClassData) {
                    Console::debug(sprintf(
                        'Loaded %s%s:',
                        $type,
                        $collect ? '' : ' (inherited)',
                    ), $data->getFqcn());
                }
                if (!$collect || !$this->filterEntity($data)) {
                    return false;
                }
                if ($data instanceof ClassData || !$data->InheritedFrom) {
                    Profile::count($type, 'type');
                }
                if ($data instanceof ClassData && $data->Lines !== null) {
                    Profile::add($data->Lines, 'line', 'type');
                }
                return true;
            },
            Console::getInstance(),
            $this->Data,
            $this->Index,
        );
    }

    /**
     * @param ClassData|ConstantData|PropertyData|MethodData $entity
     */
    private function filterEntity($entity): bool
    {
        if ($this->SkipIndex['internal'] && $entity->Internal) {
            return false;
        }

        if ($entity instanceof ClassData) {
            return true;
        }

        if ($this->SkipIndex['private'] && $entity->IsPrivate) {
            return false;
        }

        if ($entity->InheritedFrom && (
            $this->SkipIndex['inherited']
            || ($this->SkipFromIndex[Get::fqcn($entity->InheritedFrom[0])] ?? false)
        )) {
            return false;
        }

        return true;
    }
}
