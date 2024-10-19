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

/**
 * A PSR-5 PHPDoc
 *
 * Summaries that break over multiple lines are unwrapped. Descriptions and tags
 * may contain Markdown, including fenced code blocks.
 */
final class PHPDoc implements Immutable
{
    use HasMutator;

    private const PHP_DOCBLOCK = '`^' . PHPDocRegex::PHP_DOCBLOCK . '$`D';
    private const PHPDOC_TAG = '`^' . PHPDocRegex::PHPDOC_TAG . '`';
    private const BLANK_OR_PHPDOC_TAG = '`^(?:$|' . PHPDocRegex::PHPDOC_TAG . ')`D';
    private const PHPDOC_TYPE = '`^' . PHPDocRegex::PHPDOC_TYPE . '$`D';

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

    private const STANDARD_TAGS = [
        'param',
        'readonly',
        'return',
        'throws',
        'var',
        'template',
        'template-covariant',
        'template-contravariant',
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
    /** @var class-string|null */
    private ?string $Class;
    private ?string $Member;

    // --

    /** @var string[] */
    private array $Lines;
    private ?string $NextLine;

    /**
     * Creates a new PHPDoc object from a PHP DocBlock
     *
     * @param self|string|null $classDocBlock
     * @param class-string|null $class
     */
    public function __construct(
        string $docBlock,
        $classDocBlock = null,
        ?string $class = null,
        ?string $member = null
    ) {
        if (!Regex::match(self::PHP_DOCBLOCK, $docBlock, $matches)) {
            throw new InvalidArgumentException('Invalid DocBlock');
        }

        $this->Class = $class;
        $this->Member = $member;
        $this->parse($matches['content']);

        // Merge templates from the declaring class, if possible
        if ($classDocBlock !== null) {
            $phpDoc = $classDocBlock instanceof self
                ? $classDocBlock
                : new self($classDocBlock, null, $this->Class);
            foreach ($phpDoc->Templates as $name => $tag) {
                $this->Templates[$name] ??= $tag;
            }
        }
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
        foreach ($parent->Tags as $name => $theirs) {
            foreach ($theirs as $tag) {
                if ($name === 'template' || !isset($idx[(string) $tag])) {
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

        $templates = $this->Templates;
        foreach ($parent->Templates as $name => $theirs) {
            $templates[$name] ??= $theirs;
        }

        return $this
            ->with('Summary', $this->Summary ?? $parent->Summary)
            ->with('Description', $this->Description ?? $parent->Description)
            ->with('Tags', $tags)
            ->with('Params', $params)
            ->with('Return', $return)
            ->with('Vars', $vars)
            ->with('Templates', $templates);
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
                $varKey = Arr::keyOf($tags['var'] ?? [], $var);
                $vars = [$var = $var->withDescription(null)];
                $tags['var'][$varKey] = $var;
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
        if ($includeInherited || $this->Class === null) {
            return $this->Templates;
        }

        foreach ($this->Templates as $name => $template) {
            if ($template->getClass() === $this->Class && (
                $this->Member === null
                || $template->getMember() === $this->Member
            )) {
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
        $member = $tag->getMember();
        if ($class === null || $this->Class === null) {
            return $this->Templates;
        }

        /** @var TemplateTag $template */
        foreach ($this->Tags['template'] ?? [] as $template) {
            $name = $template->getName();
            if ($template->getClass() === $class && (
                ($_member = $template->getMember()) === null
                || $_member === $member
            )) {
                $templates[$name] = $template;
            }
        }

        return $templates ?? [];
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
        return $this->Summary === null
            && $this->Description === null
            && (!$this->Tags || array_keys($this->Tags) === ['inheritDoc']);
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

        foreach ([...$this->Params, $this->Return, ...$this->Vars] as $tag) {
            if (
                $tag
                && ($description = $tag->getDescription()) !== null
                && $description !== $this->Summary
            ) {
                return true;
            }
        }

        foreach (array_diff(
            array_keys($this->Tags),
            self::STANDARD_TAGS,
        ) as $key) {
            if (!Regex::match('/^(phpstan|psalm)-/', $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates a new PHPDoc object from an array of PHP DocBlocks, each of which
     * inherits from subsequent blocks
     *
     * @param array<class-string|int,string> $docBlocks
     * @param array<class-string|int,self|string|null>|null $classDocBlocks
     */
    public static function fromDocBlocks(
        array $docBlocks,
        ?array $classDocBlocks = null,
        ?string $member = null
    ): self {
        foreach ($docBlocks as $key => $docBlock) {
            $_phpDoc = new self(
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

        return $phpDoc ?? new self('/** */');
    }

    /**
     * Normalise a PHPDoc type
     *
     * If `$strict` is `true`, an exception is thrown if `$type` is not a valid
     * PHPDoc type.
     */
    public static function normaliseType(string $type, bool $strict = false): string
    {
        if (!Regex::match(self::PHPDOC_TYPE, trim($type), $matches)) {
            if ($strict) {
                throw new InvalidArgumentException(sprintf(
                    "Invalid PHPDoc type '%s'",
                    $type,
                ));
            }
            return self::replace([$type])[0];
        }

        $types = Str::splitDelimited('|', $type, true, null, Str::PRESERVE_QUOTED);

        // Move `null` to the end of union types
        $notNull = [];
        foreach ($types as $t) {
            $t = ltrim($t, '?');
            if (strcasecmp($t, 'null')) {
                $notNull[] = $t;
            }
        }

        if ($notNull !== $types) {
            $types = $notNull;
            $nullable = true;
        }

        // Simplify composite types
        $phpTypeRegex = Regex::delimit('^' . Regex::PHP_TYPE . '$', '/');
        foreach ($types as &$type) {
            $brackets = false;
            if ($type !== '' && $type[0] === '(' && $type[-1] === ')') {
                $brackets = true;
                $type = substr($type, 1, -1);
            }
            $split = array_unique(self::replace(explode('&', $type)));
            $type = implode('&', $split);
            if ($brackets && (
                count($split) > 1
                || !Regex::match($phpTypeRegex, $type)
            )) {
                $type = "($type)";
            }
        }

        $types = array_unique(self::replace($types));
        if ($nullable ?? false) {
            $types[] = 'null';
        }

        return implode('|', $types);
    }

    /**
     * @param string[] $types
     * @return string[]
     */
    private static function replace(array $types): array
    {
        return Regex::replace(
            ['/\bclass-string<(?:mixed|object)>/i', '/(?:\bmixed&|&mixed\b)/i'],
            ['class-string', ''],
            $types,
        );
    }

    private function parse(string $content): void
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
                    $this->Params[$name] = $this->Tags[$tag][] = new ParamTag(
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
                    $this->Return = $this->Tags[$tag][] = new ReturnTag(
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
                    $var = $this->Tags[$tag][] = new VarTag(
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
                    $this->Templates[$name] = $this->Tags['template'][] = new TemplateTag(
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
                    break;
            }
        }
    }

    /**
     * Collect and implode $this->NextLine and subsequent lines until, but not
     * including, the next line that matches $pattern
     *
     * If `$unwrap` is `false`, `$pattern` is ignored between code fences, which
     * start and end when a line contains 3 or more backticks or tildes and no
     * other text aside from an optional info string after the opening fence.
     *
     * @param bool $unwrap If `true`, lines are joined with " " instead of "\n".
     *
     * @phpstan-impure
     */
    private function getLinesUntil(
        string $pattern,
        bool $unwrap = false
    ): string {
        $lines = [];
        $inFence = false;

        do {
            $lines[] = $line = $this->getLine();

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
     * Shift the next line off the beginning of $this->Lines, assign its
     * successor to $this->NextLine, and return it
     *
     * @phpstan-impure
     */
    private function getLine(): string
    {
        if (!$this->Lines) {
            // @codeCoverageIgnoreStart
            throw new ShouldNotHappenException('No more lines');
            // @codeCoverageIgnoreEnd
        }

        $line = array_shift($this->Lines);
        $this->NextLine = Arr::first($this->Lines);

        return $line;
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
