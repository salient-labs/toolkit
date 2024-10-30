<?php declare(strict_types=1);

namespace Salient\PHPDoc;

use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\HasMutator;
use Salient\PHPDoc\Tag\AbstractTag;
use Salient\PHPDoc\Tag\GenericTag;
use Salient\PHPDoc\Tag\ParamTag;
use Salient\PHPDoc\Tag\ReturnTag;
use Salient\PHPDoc\Tag\TemplateTag;
use Salient\PHPDoc\Tag\VarTag;
use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\Arr;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use InvalidArgumentException;
use Stringable;

/**
 * A PSR-5 PHPDoc
 *
 * Summaries that break over multiple lines are unwrapped. Descriptions and tags
 * may contain Markdown, including fenced code blocks.
 */
class PHPDoc implements Immutable, Stringable
{
    use HasMutator;

    private const PHP_DOCBLOCK = '`^' . PHPDocRegex::PHP_DOCBLOCK . '$`D';
    private const PHPDOC_TAG = '`^' . PHPDocRegex::PHPDOC_TAG . '`';
    private const BLANK_OR_PHPDOC_TAG = '`^(?:$|' . PHPDocRegex::PHPDOC_TAG . ')`D';

    private const PARAM_TAG = '`^'
        . '(?:(?<param_type>' . PHPDocRegex::PHPDOC_TYPE . ')\h++)?'
        . '(?<param_reference>&\h*+)?'
        . '(?<param_variadic>\.\.\.\h*+)?'
        . '\$(?<param_name>[^\s]++)'
        . '\s*+(?<param_description>(?s).++)?'
        . '`';

    private const RETURN_TAG = '`^'
        . '(?<return_type>' . PHPDocRegex::PHPDOC_TYPE . ')'
        . '\s*+(?<return_description>(?s).++)?'
        . '`';

    private const VAR_TAG = '`^'
        . '(?<var_type>' . PHPDocRegex::PHPDOC_TYPE . ')'
        . '(?:\h++\$(?<var_name>[^\s]++))?'
        . '\s*+(?<var_description>(?s).++)?'
        . '`';

    private const TEMPLATE_TAG = '`^'
        . '(?<template_name>[^\s]++)'
        . '(?:\h++(?:of|as)\h++(?<template_type>' . PHPDocRegex::PHPDOC_TYPE . '))?'
        . '(?:\h++=\h++(?<template_default>(?&template_type)))?'
        . '`';

    /**
     * @var non-empty-array<string>
     */
    protected const DEFAULT_TAG_PREFIXES = [
        '',
        'phpstan-',
        'psalm-',
    ];

    /**
     * @var non-empty-array<string,non-empty-array<string>|bool>
     */
    protected const INHERITABLE_TAGS = [
        // PSR-19 Section 4
        'author' => false,
        'copyright' => false,
        'version' => false,
        'package' => false,
        'param' => true,
        'return' => true,
        'throws' => true,
        'var' => true,
        // "Magic" methods and properties
        'method' => true,
        'property' => true,
        'property-read' => true,
        'property-write' => true,
        'mixin' => true,
        // Generics
        'template' => true,
        'template-covariant' => ['phpstan-', 'psalm-'],
        'template-contravariant' => ['phpstan-', 'psalm-'],
        // Special parameters
        'param-out' => true,
        'param-immediately-invoked-callable' => ['', 'phpstan-'],
        'param-later-invoked-callable' => ['', 'phpstan-'],
        'param-closure-this' => ['', 'phpstan-'],
        // --
        'deprecated' => false,
        'readonly' => false,
    ];

    /**
     * @var array<string,true>
     */
    protected const MERGED_TAGS = [
        'param' => true,
        'return' => true,
        'var' => true,
        'template' => true,
    ];

    /**
     * @var string[]
     */
    protected const STANDARD_TAGS = [
        'param',
        'readonly',
        'return',
        'throws',
        'var',
        'template',
        'api',
        'internal',
        'inheritDoc',
    ];

    private ?string $Summary = null;
    private ?string $Description = null;
    /** @var array<string,AbstractTag[]> */
    private array $Tags = [];
    /** @var array<string,ParamTag> */
    private array $Params = [];
    private ?ReturnTag $Return = null;
    /** @var VarTag[] */
    private array $Vars = [];
    /** @var array<string,TemplateTag> */
    private array $Templates = [];
    /** @var array<class-string,array<string,TemplateTag>> */
    private array $InheritedTemplates = [];
    /** @var class-string|null */
    private ?string $Class;
    private ?string $Member;

    // --

    /** @var static */
    private self $Original;
    /** @var string[] */
    private array $Lines;
    private ?string $NextLine;
    /** @var array<class-string<self>,array<string,true>> */
    private static array $InheritableTagIndex;

    /**
     * Creates a new PHPDoc object from a PHP DocBlock
     *
     * @param self|string|null $classDocBlock
     * @param class-string|null $class
     */
    final public function __construct(
        ?string $docBlock = null,
        $classDocBlock = null,
        ?string $class = null,
        ?string $member = null
    ) {
        $docBlock ??= '/** */';
        if (!Regex::match(self::PHP_DOCBLOCK, $docBlock, $matches)) {
            throw new InvalidArgumentException('Invalid DocBlock');
        }

        $this->Class = $class;
        $this->Member = $member;
        $this->Original = $this;
        $this->parse($matches['content'], $tags);

        // Merge templates from the declaring class, if possible
        if ($classDocBlock !== null && $class !== null && $member !== null) {
            $phpDoc = $classDocBlock instanceof self
                ? $classDocBlock
                : new static($classDocBlock, null, $class);
            foreach ($phpDoc->Templates as $name => $tag) {
                $this->Templates[$name] ??= $tag;
            }
        }

        if ($tags) {
            $this->updateTags();
        }
    }

    /**
     * Creates a new PHPDoc object from an array of tag objects
     *
     * @param AbstractTag[] $tags
     * @param class-string|null $class
     * @return static
     */
    public static function fromTags(
        array $tags,
        ?string $summary = null,
        ?string $description = null,
        ?string $class = null,
        ?string $member = null
    ): self {
        if ($summary !== null) {
            $summary = Str::coalesce(trim($summary), null);
        }
        if ($description !== null) {
            $description = Str::coalesce(trim($description), null);
        }
        if ($summary === null && $description !== null) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('$description must be empty if $summary is empty');
            // @codeCoverageIgnoreEnd
        }

        $phpDoc = new static(null, null, $class, $member);
        $phpDoc->Summary = $summary;
        $phpDoc->Description = $description;

        $count = 0;
        foreach ($tags as $tag) {
            if ($tag instanceof ParamTag) {
                $phpDoc->Params[$tag->getName()] = $tag;
            } elseif ($tag instanceof ReturnTag) {
                $phpDoc->Return = $tag;
            } elseif ($tag instanceof VarTag) {
                $name = $tag->getName();
                if ($name !== null) {
                    $phpDoc->Vars[$name] = $tag;
                } else {
                    $phpDoc->Vars[] = $tag;
                }
            } elseif ($tag instanceof TemplateTag) {
                $_class = $tag->getClass();
                if ($_class === $class || $_class === null || $class === null) {
                    $phpDoc->Templates[$tag->getName()] = $tag;
                } else {
                    $phpDoc->InheritedTemplates[$_class][$tag->getName()] = $tag;
                    continue;
                }
            } else {
                $phpDoc->Tags[$tag->getTag()][] = $tag;
                continue;
            }

            $phpDoc->Tags[$tag->getTag()] ??= [];
            $count++;
        }

        if ($count) {
            $phpDoc->updateTags();
        }

        return $phpDoc;
    }

    /**
     * Inherit values from an instance that represents the same structural
     * element in a parent class or interface
     *
     * @return static
     */
    public function inherit(self $parent)
    {
        $tags = $this->Tags;
        foreach (Arr::flatten($tags) as $tag) {
            $idx[(string) $tag] = true;
        }
        foreach (array_intersect_key(
            $parent->Tags,
            self::getInheritableTagIndex(),
        ) as $name => $theirs) {
            foreach ($theirs as $tag) {
                if (!isset($idx[(string) $tag])) {
                    $tags[$name][] = $tag;
                }
            }
        }

        $params = $this->Params;
        foreach ($parent->Params as $name => $theirs) {
            $params[$name] = $this->mergeTag($params[$name] ?? null, $theirs);
        }

        $return = $this->mergeTag($this->Return, $parent->Return);

        $vars = $this->Vars;
        if (
            count($parent->Vars) === 1
            && array_key_first($parent->Vars) === 0
        ) {
            $vars[0] = $this->mergeTag($vars[0] ?? null, $parent->Vars[0]);
        }

        $templates = $this->InheritedTemplates;
        if ($parent->Class !== null && $parent->Class !== $this->Class) {
            unset($templates[$parent->Class]);
            $templates[$parent->Class] = $parent->Templates;
        }

        $summary = $this->Summary;
        $description = $this->Description;
        if ($description !== null && $parent->Description !== null) {
            $description = Regex::replace(
                '/\{@inherit[Dd]oc\}/',
                $parent->Description,
                $description,
            );
        } else {
            $summary ??= $parent->Summary;
            $description ??= $parent->Description;
        }

        return $this
            ->with('Summary', $summary)
            ->with('Description', $description)
            ->with('Tags', $tags)
            ->with('Params', $params)
            ->with('Return', $return)
            ->with('Vars', $vars)
            ->with('InheritedTemplates', $templates);
    }

    /**
     * @return array<string,true>
     */
    private static function getInheritableTagIndex(): array
    {
        if (isset(self::$InheritableTagIndex[static::class])) {
            return self::$InheritableTagIndex[static::class];
        }

        foreach (static::INHERITABLE_TAGS as $tag => $prefixes) {
            if ($prefixes === false) {
                $idx[$tag] = true;
                continue;
            } elseif ($prefixes === true) {
                $prefixes = static::DEFAULT_TAG_PREFIXES;
            }
            foreach ($prefixes as $prefix) {
                $idx[$prefix . $tag] = true;
            }
        }

        return self::$InheritableTagIndex[static::class] = array_diff_key(
            $idx,
            static::MERGED_TAGS,
        );
    }

    /**
     * @template T of AbstractTag
     *
     * @param T|null $ours
     * @param T|null $theirs
     * @return ($ours is null ? ($theirs is null ? null : T) : T)
     */
    private function mergeTag(?AbstractTag $ours, ?AbstractTag $theirs): ?AbstractTag
    {
        if ($theirs === null) {
            return $ours;
        }

        if ($ours === null) {
            return $theirs;
        }

        return $ours->inherit($theirs);
    }

    /**
     * Get a normalised instance
     *
     * If the PHPDoc has one `@var` tag with no variable name, its description
     * is applied to the summary or description of the PHPDoc and removed from
     * the tag.
     *
     * `@inheritDoc` tags are removed.
     *
     * @return static
     */
    public function normalise()
    {
        $tags = $this->Tags;
        unset($tags['inheritDoc']);

        $vars = $this->Vars;
        if (count($vars) === 1 && array_key_first($vars) === 0) {
            $var = $vars[0];
            $varDesc = $var->getDescription();
            if ($varDesc !== null) {
                $vars = [$var->withDescription(null)];
                if ($this->Summary === null) {
                    $summary = $varDesc;
                } elseif ($this->Summary !== $varDesc) {
                    $description = Arr::implode("\n\n", [
                        $this->Description,
                        $varDesc,
                    ], '');
                }
            }
        }

        return $this
            ->with('Summary', $summary ?? $this->Summary)
            ->with('Description', $description ?? $this->Description)
            ->with('Tags', $tags)
            ->with('Vars', $vars);
    }

    /**
     * Flatten values inherited from other instances and forget the initial
     * state of the PHPDoc
     *
     * @return static
     */
    public function flatten()
    {
        $phpDoc = clone $this;
        $phpDoc->Original = $phpDoc;
        $phpDoc->InheritedTemplates = [];

        return $phpDoc;
    }

    /**
     * Called after a property of the PHPDoc is changed via with()
     */
    private function handlePropertyChanged(string $property): void
    {
        $tag = [
            'Params' => 'param',
            'Return' => 'return',
            'Vars' => 'var',
            'Templates' => 'template',
        ][$property] ?? null;

        if ($tag !== null) {
            $this->updateTags($tag);
        }
    }

    private function updateTags(?string $tag = null): void
    {
        if ($tag === 'param' || $tag === null) {
            if ($this->Params) {
                $this->Tags['param'] = array_values($this->Params);
            } else {
                unset($this->Tags['param']);
            }
        }

        if ($tag === 'return' || $tag === null) {
            if ($this->Return) {
                $this->Tags['return'] = [$this->Return];
            } else {
                unset($this->Tags['return']);
            }
        }

        if ($tag === 'var' || $tag === null) {
            if ($this->Vars) {
                $this->Tags['var'] = array_values($this->Vars);
            } else {
                unset($this->Tags['var']);
            }
        }

        if ($tag === 'template' || $tag === null) {
            if ($templates = $this->getTemplates(false)) {
                $this->Tags['template'] = array_values($templates);
            } else {
                unset($this->Tags['template']);
            }
        }
    }

    /**
     * Get the state of the PHPDoc before inheriting values from other instances
     *
     * @return static
     */
    public function getOriginal()
    {
        return $this->Original;
    }

    /**
     * Get the PHPDoc's summary (if provided)
     */
    public function getSummary(): ?string
    {
        return $this->Summary;
    }

    /**
     * Get the PHPDoc's description (if provided)
     */
    public function getDescription(): ?string
    {
        return $this->Description;
    }

    /**
     * Check if the PHPDoc has a tag with the given name
     */
    public function hasTag(string $name): bool
    {
        if ($name !== '' && $name[0] === '@') {
            $name = substr($name, 1);
        }
        return isset($this->Tags[$name]);
    }

    /**
     * Get the PHPDoc's tags, indexed by tag name
     *
     * @return array<string,AbstractTag[]>
     */
    public function getTags(): array
    {
        return $this->Tags;
    }

    /**
     * Get the PHPDoc's "@param" tags, indexed by name
     *
     * @return array<string,ParamTag>
     */
    public function getParams(): array
    {
        return $this->Params;
    }

    /**
     * Check if the PHPDoc has a "@return" tag
     *
     * @phpstan-assert-if-true !null $this->getReturn()
     */
    public function hasReturn(): bool
    {
        return $this->Return !== null;
    }

    /**
     * Get the PHPDoc's "@return" tag (if provided)
     */
    public function getReturn(): ?ReturnTag
    {
        return $this->Return;
    }

    /**
     * Get the PHPDoc's "@var" tags
     *
     * @return VarTag[]
     */
    public function getVars(): array
    {
        return $this->Vars;
    }

    /**
     * Get the PHPDoc's "@template" tags, indexed by name
     *
     * @return array<string,TemplateTag>
     */
    public function getTemplates(bool $includeInherited = true): array
    {
        if (
            $includeInherited
            || $this->Class === null
            || $this->Member === null
        ) {
            return $this->Templates;
        }

        foreach ($this->Templates as $name => $template) {
            if ($template->getMember() !== null) {
                $templates[$name] = $template;
            }
        }

        return $templates ?? [];
    }

    /**
     * Get "@template" tags applicable to the given tag, indexed by name
     *
     * @return array<string,TemplateTag>
     */
    public function getTemplatesForTag(AbstractTag $tag): array
    {
        $class = $tag->getClass();
        if (
            $class === $this->Class
            || $class === null
            || $this->Class === null
        ) {
            return $this->Templates;
        }

        return $this->InheritedTemplates[$class] ?? [];
    }

    /**
     * Get the name of the class associated with the PHPDoc
     */
    public function getClass(): ?string
    {
        return $this->Class;
    }

    /**
     * Get the class member associated with the PHPDoc
     */
    public function getMember(): ?string
    {
        return $this->Member;
    }

    /**
     * Check if the PHPDoc has no content
     */
    public function isEmpty(): bool
    {
        return $this->Original->Summary === null
            && $this->Original->Description === null
            && !$this->Original->Tags;
    }

    /**
     * Check if the PHPDoc has data other than a summary and standard type
     * information
     */
    public function hasDetail(): bool
    {
        if ($this->Description !== null) {
            return true;
        }

        foreach ([$this->Params, [$this->Return], $this->Vars] as $tags) {
            foreach ($tags as $tag) {
                if (
                    $tag
                    && ($description = $tag->getDescription()) !== null
                    && $description !== $this->Summary
                ) {
                    return true;
                }
            }
        }

        foreach (array_diff(
            array_keys($this->Tags),
            static::STANDARD_TAGS,
        ) as $key) {
            if (!Regex::match('/^(phpstan|psalm)-/', $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $tags = Arr::flatten(Arr::unset($this->Tags, 'template'));

        $text = Arr::implode("\n\n", [
            $this->Summary,
            $this->Description,
            Arr::implode("\n", $this->getTemplates(false), ''),
            Arr::implode("\n", $tags, ''),
        ], '');

        if ($text === '') {
            return '/** */';
        }

        if (strpos($text, "\n") === false && $text[0] === '@') {
            return "/** {$text} */";
        }

        return "/**\n * " . Regex::replace(
            ["/\n(?!\n)/", "/\n(?=\n)/"],
            ["\n * ", "\n *"],
            $text,
        ) . "\n */";
    }

    /**
     * Creates a new PHPDoc object from an array of PHP DocBlocks, each of which
     * inherits from subsequent blocks
     *
     * @param array<class-string|int,string|null> $docBlocks
     * @param array<class-string|int,self|string|null>|null $classDocBlocks
     * @return static
     */
    public static function fromDocBlocks(
        array $docBlocks,
        ?array $classDocBlocks = null,
        ?string $member = null
    ): self {
        foreach ($docBlocks as $key => $docBlock) {
            $_phpDoc = new static(
                $docBlock,
                $classDocBlocks[$key] ?? null,
                is_string($key) ? $key : null,
                $member,
            );

            $phpDoc ??= $_phpDoc;
            if ($_phpDoc !== $phpDoc) {
                $phpDoc = $phpDoc->inherit($_phpDoc);
            }
        }

        return $phpDoc ?? new static();
    }

    private function parse(string $content, ?int &$tags): void
    {
        // - Remove leading asterisks after newlines
        // - Trim the entire PHPDoc
        // - Remove trailing whitespace and split into string[]
        $this->Lines = Regex::split(
            '/\h*+(?:\r\n|\n|\r)/',
            trim(Regex::replace('/(?<=\r\n|\n|\r)\h*+\* ?/', '', $content)),
        );
        $this->NextLine = Arr::first($this->Lines);

        if (!Regex::match(self::PHPDOC_TAG, $this->NextLine)) {
            $this->Summary = Str::coalesce(
                $this->getLinesUntil(self::BLANK_OR_PHPDOC_TAG, true),
                null,
            );

            if (
                $this->NextLine !== null
                && !Regex::match(self::PHPDOC_TAG, $this->NextLine)
            ) {
                $this->Description = Str::coalesce(
                    trim($this->getLinesUntil(self::PHPDOC_TAG)),
                    null,
                );
            }
        }

        $tags = 0;
        while ($this->Lines && Regex::match(
            self::PHPDOC_TAG,
            $text = $this->getLinesUntil(self::PHPDOC_TAG),
            $matches,
        )) {
            // Remove the tag name and trim whatever remains
            $text = trim(substr($text, strlen($matches[0])));
            $tag = ltrim($matches['tag'], '\\');

            switch ($tag) {
                // @param [type] $<name> [description]
                case 'param':
                    if (!Regex::match(self::PARAM_TAG, $text, $matches, \PREG_UNMATCHED_AS_NULL)) {
                        $this->throw('Invalid syntax', $tag);
                    }
                    /** @var string */
                    $name = $matches['param_name'];
                    $this->Params[$name] = new ParamTag(
                        $name,
                        $matches['param_type'],
                        $matches['param_reference'] !== null,
                        $matches['param_variadic'] !== null,
                        $matches['param_description'],
                        $this->Class,
                        $this->Member,
                    );
                    break;

                // @return <type> [description]
                case 'return':
                    if (!Regex::match(self::RETURN_TAG, $text, $matches, \PREG_UNMATCHED_AS_NULL)) {
                        $this->throw('Invalid syntax', $tag);
                    }
                    /** @var string */
                    $type = $matches['return_type'];
                    $this->Return = new ReturnTag(
                        $type,
                        $matches['return_description'],
                        $this->Class,
                        $this->Member,
                    );
                    break;

                // @var <type> [$<name>] [description]
                case 'var':
                    if (!Regex::match(self::VAR_TAG, $text, $matches, \PREG_UNMATCHED_AS_NULL)) {
                        $this->throw('Invalid syntax', $tag);
                    }
                    /** @var string */
                    $type = $matches['var_type'];
                    $name = $matches['var_name'];
                    $var = new VarTag(
                        $type,
                        $name,
                        $matches['var_description'],
                        $this->Class,
                        $this->Member,
                    );
                    if ($name !== null) {
                        $this->Vars[$name] = $var;
                    } else {
                        $this->Vars[] = $var;
                    }
                    break;

                // - @template <name> [of <type>] [= <type>]
                // - @template-(covariant|contravariant) <name> [of <type>] [= <type>]
                case 'template-covariant':
                case 'template-contravariant':
                case 'template':
                    if (!Regex::match(self::TEMPLATE_TAG, $text, $matches, \PREG_UNMATCHED_AS_NULL)) {
                        $this->throw('Invalid syntax', $tag);
                    }
                    /** @var string */
                    $name = $matches['template_name'];
                    $type = $matches['template_type'];
                    $default = $matches['template_default'];
                    /** @var "covariant"|"contravariant"|null */
                    $variance = explode('-', $tag, 2)[1] ?? null;
                    $tag = 'template';
                    $this->Templates[$name] = new TemplateTag(
                        $name,
                        $type,
                        $default,
                        $variance,
                        $this->Class,
                        $this->Member,
                    );
                    break;

                default:
                    $this->Tags[$tag][] = new GenericTag(
                        $tag,
                        Str::coalesce($text, null),
                        $this->Class,
                        $this->Member,
                    );
                    continue 2;
            }

            $this->Tags[$tag] ??= [];
            $tags++;
        }

        unset($this->Lines, $this->NextLine);
    }

    /**
     * Consume and implode $this->Lines values up to, but not including, the
     * next that matches $pattern and doesn't belong to a fenced code block
     *
     * If `$unwrap` is `true`, fenced code blocks are ignored and lines are
     * joined with `" "` instead of `"\n"`.
     *
     * @phpstan-impure
     */
    private function getLinesUntil(string $pattern, bool $unwrap = false): string
    {
        if (!$this->Lines) {
            // @codeCoverageIgnoreStart
            throw new ShouldNotHappenException('No more lines');
            // @codeCoverageIgnoreEnd
        }

        $lines = [];
        $inFence = false;

        do {
            $lines[] = $line = array_shift($this->Lines);
            $this->NextLine = Arr::first($this->Lines);

            if (!$unwrap) {
                if (
                    (!$inFence && Regex::match('/^(```+|~~~+)/', $line, $fence))
                    || ($inFence && isset($fence[0]) && $line === $fence[0])
                ) {
                    $inFence = !$inFence;
                }

                if ($inFence) {
                    continue;
                }
            }

            if ($this->NextLine === null) {
                break;
            }

            if (Regex::match($pattern, $this->NextLine)) {
                break;
            }
        } while ($this->Lines);

        if ($inFence) {
            throw new InvalidArgumentException('Unterminated code fence in DocBlock');
        }

        return implode($unwrap ? ' ' : "\n", $lines);
    }

    /**
     * @param string|int|float ...$args
     * @return never
     */
    private function throw(string $message, ?string $tag, ...$args): void
    {
        if ($tag !== null) {
            $message .= ' for @%s';
            $args[] = $tag;
        }

        $message .= ' in DocBlock';

        if (isset($this->Class)) {
            $message .= ' of %s';
            $args[] = $this->Class;
            if (isset($this->Member)) {
                $message .= '::%s';
                $args[] = $this->Member;
            }
        }

        throw new InvalidArgumentException(sprintf($message, ...$args));
    }
}
