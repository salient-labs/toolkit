<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use Salient\Contract\Console\ConsoleWriterInterface;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\PHPDoc\PHPDoc;
use Salient\PHPDoc\PHPDocUtil;
use Salient\Sli\Internal\NavigableToken;
use Salient\Sli\Internal\TokenExtractor;
use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\Get;
use Salient\Utility\Str;
use BackedEnum;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Throwable;
use UnitEnum;

/**
 * @internal
 */
class ClassData implements JsonSerializable
{
    use HasPHPDoc;
    use HasTemplates;

    /**
     * Type => [ ClassData property, ReflectionClass method, TokenExtractor method, case sensitive, data class ]
     */
    public const TYPE = [
        'constant' => ['Constants', 'getReflectionConstants', 'getConstants', true, ConstantData::class],
        'property' => ['Properties', 'getProperties', 'getProperties', true, PropertyData::class],
        'method' => ['Methods', 'getMethods', 'getFunctions', false, MethodData::class],
    ];

    public string $Name;
    public string $Namespace;
    /** @var "class"|"interface"|"trait"|"enum" */
    public string $Type;
    /** @var array<string,string> */
    public array $Templates = [];
    public ?string $Summary = null;
    /** @var class-string[] */
    public array $Extends = [];
    /** @var class-string[] */
    public array $Implements = [];
    /** @var class-string[] */
    public array $Uses = [];
    public bool $Api = false;
    public bool $Internal = false;
    public bool $Deprecated = false;
    public bool $HasDocComment = false;
    public bool $IsAbstract = false;
    public bool $IsFinal = false;
    public bool $IsReadOnly = false;
    /** @var string[] */
    public array $Modifiers = [];
    /** @var array<string,ConstantData> */
    public array $Constants = [];
    /** @var array<string,PropertyData> */
    public array $Properties = [];
    /** @var array<string,MethodData> */
    public array $Methods = [];
    public ?string $File = null;
    public ?int $Line = null;
    public ?int $Lines = null;

    /**
     * @param "class"|"interface"|"trait"|"enum" $type
     */
    final public function __construct(string $name, string $namespace = '', string $type = 'class')
    {
        $this->Name = $name;
        $this->Namespace = $namespace;
        $this->Type = $type;
    }

    /**
     * @param (callable(ConstantData|PropertyData|MethodData $data, string $type): bool)|null $filter
     * @param ReflectionClass<object> $class
     * @return static
     */
    public static function fromExtractor(
        TokenExtractor $extractor,
        ReflectionClass $class,
        ?callable $filter = null,
        ?ConsoleWriterInterface $console = null
    ): self {
        if (
            !($nsExtractor = $extractor->getParent())
            || !$nsExtractor->hasNamespace()
            || $nsExtractor->getParent()->getParent()
            || !$extractor->hasClass()
        ) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('Invalid token extractor');
            // @codeCoverageIgnoreEnd
        }

        $token = $extractor->getClassToken();
        try {
            $docBlocks = PHPDocUtil::getAllClassDocComments($class, true);
            $phpDoc = PHPDoc::fromDocBlocks($docBlocks);
            self::checkPHPDoc($phpDoc, $console);
        } catch (Throwable $ex) {
            !$console || $console->exception($ex, Level::WARNING, null);
            $phpDoc = new PHPDoc();
        }

        $data = (new static(
            $extractor->getClass(),
            $nsExtractor->getNamespace(),
            NamespaceData::TYPE_MAP[$token->id],
        ))
            ->applyPHPDoc($phpDoc)
            ->applyTemplates($phpDoc);

        $data->File = $extractor->getFilename();
        $start = $class->getStartLine();
        $end = $class->getEndLine();
        if ($start === false || $end === false) {
            // @codeCoverageIgnoreStart
            !$console || $console->warn(
                'Cannot get class start/end lines via reflection:',
                $data->getFqcn(),
            );
            $data->Line = $token->line;
            // @codeCoverageIgnoreEnd
        } else {
            $data->Line = $start;
            $data->Lines = $end - $start + 1;
        }

        if ($token->id === \T_CLASS) {
            $data->applyModifiers($class, $phpDoc);
        }

        if (
            ($token->id === \T_CLASS || $token->id === \T_INTERFACE)
            && ($next = $token->NextCode)
            && ($next = $next->NextCode)
        ) {
            if ($next->id === \T_EXTENDS) {
                $data->Extends = self::getNameList($next, $nsExtractor);
            }
            if ($next->id === \T_IMPLEMENTS) {
                $data->Implements = self::getNameList($next, $nsExtractor);
            }
        } elseif ($token->id === \T_ENUM) {
            $next = $token;
            while (
                ($next = $next->getNextSibling())
                && $next->id !== \T_OPEN_BRACE
            ) {
                if ($next->id === \T_IMPLEMENTS) {
                    $data->Implements = self::getNameList($next, $nsExtractor);
                    break;
                }
            }
        }

        if ($token->id !== \T_INTERFACE) {
            $data->Uses = $class->getTraitNames();
        }

        $extractors = [];
        foreach (self::TYPE as $type => [
            $property,
            $classMethod,
            $extractorMethod,
            $caseSensitive,
            $memberClass,
        ]) {
            /** @var ReflectionClassConstant[]|ReflectionProperty[]|ReflectionMethod[] */
            $members = $class->$classMethod();
            foreach ($members as $member) {
                if (!isset($extractors[$property])) {
                    /** @var iterable<string,TokenExtractor> */
                    $children = $extractor->$extractorMethod();
                    $extractors[$property] = Get::array($children);
                    if (!$caseSensitive) {
                        $extractors[$property] = array_change_key_case($extractors[$property]);
                    }
                }
                $name = $member->getName();
                $_name = $caseSensitive ? $name : Str::lower($name);
                $line = ($memberExtractor = $extractors[$property][$_name] ?? null)
                    && ($memberToken = $memberExtractor->getMemberToken())
                        ? $memberToken->line
                        : null;
                $memberData = $memberClass::fromReflection(
                    $member,
                    $class,
                    $data,
                    (bool) $memberExtractor,
                    $line,
                    $console,
                );
                if ($filter && !$filter($memberData, $type)) {
                    $memberData->Hide = true;
                }
                $data->$property[$name] = $memberData;
            }
        }

        return $data;
    }

    /**
     * @param (callable(ConstantData|PropertyData|MethodData $data, string $type): bool)|null $filter
     * @param ReflectionClass<object> $class
     * @return static
     */
    public static function fromReflection(
        ReflectionClass $class,
        ?callable $filter = null,
        ?ConsoleWriterInterface $console = null
    ): self {
        if ($class->isAnonymous()) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('$class cannot be anonymous');
            // @codeCoverageIgnoreEnd
        }

        $id = $class->isInterface()
            ? \T_INTERFACE
            : ($class->isTrait()
                ? \T_TRAIT
                : (\PHP_VERSION_ID >= 80100 && $class->isEnum()
                    ? \T_ENUM
                    : \T_CLASS));

        try {
            $docBlocks = PHPDocUtil::getAllClassDocComments($class, true);
            $phpDoc = PHPDoc::fromDocBlocks($docBlocks);
            self::checkPHPDoc($phpDoc, $console);
        } catch (Throwable $ex) {
            !$console || $console->exception($ex, Level::WARNING, null);
            $phpDoc = new PHPDoc();
        }

        $data = (new static(
            $class->getShortName(),
            $class->getNamespaceName(),
            NamespaceData::TYPE_MAP[$id],
        ))
            ->applyPHPDoc($phpDoc)
            ->applyTemplates($phpDoc);

        $file = $class->getFileName();
        if ($file !== false) {
            $data->File = $file;
        }
        $start = $class->getStartLine();
        $end = $class->getEndLine();
        if ($start === false || $end === false) {
            // @codeCoverageIgnoreStart
            $file === false || !$console || $console->warn(
                'Cannot get class start/end lines:',
                $class->getName(),
            );
            // @codeCoverageIgnoreEnd
        } else {
            $data->Line = $start;
            $data->Lines = $end - $start + 1;
        }

        if ($id === \T_INTERFACE) {
            $data->Extends = $class->getInterfaceNames();
        } else {
            if ($id === \T_CLASS) {
                if ($parent = $class->getParentClass()) {
                    $data->Extends[] = $parent->getName();
                }
                $data->applyModifiers($class, $phpDoc);
            }
            if ($id !== \T_TRAIT) {
                $implements = $class->getInterfaceNames();
                if ($id === \T_ENUM) {
                    $implements = array_diff($implements, [
                        UnitEnum::class,
                        BackedEnum::class,
                    ]);
                }
                $data->Implements = $implements;
            }
            $data->Uses = $class->getTraitNames();
        }

        foreach (self::TYPE as $type => [
            $property,
            $classMethod,,,
            $memberClass,
        ]) {
            /** @var ReflectionClassConstant[]|ReflectionProperty[]|ReflectionMethod[] */
            $members = $class->$classMethod();
            foreach ($members as $member) {
                $memberData = $memberClass::fromReflection(
                    $member,
                    $class,
                    $data,
                    null,
                    null,
                    $console,
                );
                if ($filter && !$filter($memberData, $type)) {
                    $memberData->Hide = true;
                }
                $data->$property[$member->getName()] = $memberData;
            }
        }

        return $data;
    }

    /**
     * @param-out NavigableToken $token
     * @return class-string[]
     */
    private static function getNameList(NavigableToken &$token, TokenExtractor $extractor): array
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
    private function applyModifiers(ReflectionClass $class, PHPDoc $phpDoc): void
    {
        $this->Modifiers = array_keys(array_filter([
            'abstract' => $this->IsAbstract = $class->isAbstract(),
            'final' => $this->IsFinal = $class->isFinal(),
            'readonly' => $this->IsReadOnly = \PHP_VERSION_ID >= 80200 && $class->isReadOnly(),
        ]));
        $this->IsFinal = $this->IsFinal || $phpDoc->hasTag('final');
        $this->IsReadOnly = $this->IsReadOnly || $phpDoc->hasTag('readonly');
    }

    /**
     * @return class-string
     */
    public function getFqcn(): string
    {
        /** @var class-string */
        return $this->Namespace === ''
            ? $this->Name
            : $this->Namespace . '\\' . $this->Name;
    }

    public function sort(ClassDataFactory $factory): void
    {
        foreach (self::TYPE as [$property]) {
            /** @var array<string,ConstantData|PropertyData|MethodData> */
            $members = &$this->$property;
            if (!$members) {
                continue;
            }

            // 1. Declared items, sorted by line number
            // 2. Inherited items, sorted by:
            //    - class inherited from
            //    - declaring class or trait
            //    - line number
            //    - name
            uasort(
                $members,
                fn($a, $b) =>
                    ($a->Line ?? \PHP_INT_MAX) <=> ($b->Line ?? \PHP_INT_MAX)
                        ?: ($a->InheritedFrom[0] ?? '') <=> ($b->InheritedFrom[0] ?? '')
                        ?: ($_a = $this->getInheritedMemberData($a, $factory))->Class->getFqcn()
                            <=> ($_b = $this->getInheritedMemberData($b, $factory))->Class->getFqcn()
                            ?: ($_a->Line ?? \PHP_INT_MAX) <=> ($_b->Line ?? \PHP_INT_MAX)
                            ?: strcasecmp($a->Name, $b->Name),
            );
        }
    }

    /**
     * @template T of ConstantData|PropertyData|MethodData
     *
     * @param T $data
     * @return T
     */
    private function getInheritedMemberData($data, ClassDataFactory $factory)
    {
        [$property, $prefix, $suffix] = $data instanceof ConstantData
            ? ['Constants', '', '']
            : ($data instanceof PropertyData
                ? ['Properties', '$', '']
                : ['Methods', '', '()']);

        while ($data->InheritedFrom) {
            [$class, $member] = $data->InheritedFrom;
            $from = $factory->getClassData($class);
            $data = $from->$property[$member] ?? null;
            if (!$data) {
                throw new ShouldNotHappenException(sprintf(
                    'Unable to resolve %s::%s%s%s',
                    $class,
                    $prefix,
                    $member,
                    $suffix,
                ));
            }
        }

        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'templates' => $this->Templates ?: new stdClass(),
            'summary' => $this->Summary,
            'extends' => $this->Extends,
            'implements' => $this->Implements,
            'uses' => $this->Uses,
            'api' => $this->Api,
            'internal' => $this->Internal,
            'deprecated' => $this->Deprecated,
            'hasDocComment' => $this->HasDocComment,
            'abstract' => $this->IsAbstract,
            'final' => $this->IsFinal,
            'readonly' => $this->IsReadOnly,
            'modifiers' => $this->Modifiers,
            'constants' => self::filterMembers($this->Constants) ?: new stdClass(),
            'properties' => self::filterMembers($this->Properties) ?: new stdClass(),
            'methods' => self::filterMembers($this->Methods) ?: new stdClass(),
            'file' => $this->File,
            'line' => $this->Line,
            'lines' => $this->Lines,
        ];
    }

    /**
     * @template T of ConstantData|PropertyData|MethodData
     *
     * @param array<string,T> $members
     * @return array<string,T>
     */
    public static function filterMembers(array $members): array
    {
        foreach ($members as $name => $data) {
            if ($data->Hide) {
                unset($members[$name]);
            }
        }
        return $members;
    }
}
