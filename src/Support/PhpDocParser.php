<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use UnexpectedValueException;

/**
 * Partially parses PSR-5 PHPDocs
 *
 * Newlines and other formatting are preserved unless otherwise noted.
 *
 * @property-read string|null $Summary
 * @property-read string|null $Description
 * @property-read string[] $TagLines
 * @property-read array<string,string[]|true> $Tags
 * @property-read array<string,array{type:string|null,description:string|null}> $Params
 * @property-read array{type:string,description:string|null}|null $Return
 */
class PhpDocParser implements IReadable
{
    use TFullyReadable;

    /**
     * @link https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md#53-tags
     */
    public const TAG_REGEX = '/^@(?P<tag>[[:alpha:]\\\\][[:alnum:]\\\\_-]*)(?:\h|[(:]|$)/';

    /**
     * @link https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md#appendix-a-types
     */
    public const TYPE_PATTERN = '(?:\\\\?[[:alpha:]_\x80-\xff][[:alnum:]_\x80-\xff]*)+';
    public const TYPE_REGEX   = '/^' . self::TYPE_PATTERN . '$/';

    /**
     * The summary, if provided
     *
     * Newlines are removed.
     *
     * @internal
     * @var string|null
     */
    protected $Summary;

    /**
     * The description, if provided
     *
     * @internal
     * @var string|null
     */
    protected $Description;

    /**
     * The full text of each tag, in order of appearance
     *
     * @internal
     * @var string[]
     */
    protected $TagLines = [];

    /**
     * Tag name => tag content, in order of appearance
     *
     * Tags that only appear by themselves (i.e. without a description or any
     * other metadata) are added with the boolean value `true`.
     *
     * @internal
     * @var array<string,string[]|true>
     */
    protected $Tags = [];

    /**
     * Parameter name => parameter metadata
     *
     * @internal
     * @var array<string,array{type:string|null,description:string|null}>
     */
    protected $Params = [];

    /**
     * Return value metadata, if provided
     *
     * @internal
     * @var array{type:string,description:string|null}|null
     */
    protected $Return;

    /**
     * @var bool
     */
    private $LegacyNullable;

    /**
     * @var string[]
     */
    private $Lines;

    /**
     * @var string|false
     */
    private $NextLine;

    /**
     * @param bool $legacyNullable If set (the default), convert `<type>|null`
     * and `null|<type>` to `?<type>`.
     */
    public function __construct(string $docBlock, bool $legacyNullable = true)
    {
        $this->LegacyNullable = $legacyNullable;

        // Check for a leading "*" after every newline and extract everything
        // between "/**" and "*/"
        if (!preg_match('/^\/\*\*(.*(?:(?:\r\n|\n)\h*\*.*)*)(?:(?:\r\n|\n)\h*)?\*\/$/u', $docBlock, $matches))
        {
            throw new UnexpectedValueException("Invalid DocBlock");
        }

        // Trim each line and remove the leading "* " or "*" before splitting
        $this->Lines = preg_split(
            '/\r\n|\n/u',
            preg_replace('/(^\h*\* ?|\h+$)/um', "", trim($matches[1]))
        );
        $this->NextLine = reset($this->Lines);

        if ($this->NextLine !== false && !preg_match(self::TAG_REGEX, $this->NextLine))
        {
            $this->Summary = $this->getLinesUntil('/^$/', true, true);

            if ($this->NextLine !== false && !preg_match(self::TAG_REGEX, $this->NextLine))
            {
                $this->Description = rtrim($this->getLinesUntil(self::TAG_REGEX));
            }
        }

        while ($this->Lines &&
            preg_match(self::TAG_REGEX, $lines = $this->getLinesUntil(self::TAG_REGEX), $matches))
        {
            $this->TagLines[] = $lines;

            $lines = preg_replace('/^@' . str_replace("\\", "\\\\", $matches["tag"]) . '\h*/', "", $lines);
            $tag   = ltrim($matches["tag"], "\\");
            $this->Tags[$tag][] = $lines;

            if (!$lines)
            {
                continue;
            }

            $meta = 0;
            switch ($tag)
            {
                case "param":
                    $token = strtok($lines, " \t");
                    $type  = null;
                    if (!preg_match('/^\$/', $token))
                    {
                        $type = $token;
                        $meta++;
                        $token = strtok(" \t\n\r");
                        if ($token === false || !preg_match('/^\$/', $token))
                        {
                            continue 2;
                        }
                    }
                    if ($name = substr($token, 1))
                    {
                        $meta++;
                        $this->Params[$name] = $this->getValue($type, $lines, $meta);
                    }
                    break;

                case "return":
                    $token = strtok($lines, " \t\n\r");
                    $type  = $token;
                    $meta++;
                    $this->Return = $this->getValue($type, $lines, $meta);
                    break;
            }
        }

        // Release strtok's copy of the string most recently passed to it
        strtok("", "");

        // Replace tags that have no content with `true`
        $this->Tags = array_map(
            fn(array $tag) => count(array_filter($tag)) ? $tag : true,
            $this->Tags
        );
    }

    private function getValue(?string $type, string $lines, int $meta): array
    {
        if ($this->LegacyNullable && !is_null($type))
        {
            $nullable = preg_replace('/^null\||\|null$/', "", $type, 1, $count);
            if ($count && preg_match(self::TYPE_REGEX, $nullable))
            {
                $type = "?$nullable";
            }
        }
        return [
            "type"        => $type,
            "description" => preg_split('/\s+/', $lines, $meta + 1)[$meta] ?? null
        ];
    }

    private function getLine(): string
    {
        $line           = array_shift($this->Lines);
        $this->NextLine = reset($this->Lines);
        return $line;
    }

    private function getLinesUntil(string $pattern, bool $discard = false, bool $unwrap = false): string
    {
        $lines   = [];
        $inFence = false;

        do
        {
            $lines[] = $line = $this->getLine();

            if ((!$inFence && preg_match('/^```+/', $line, $fence)) ||
                ($inFence && $line == ($fence[0] ?? null)))
            {
                $inFence = !$inFence;
            }
            if ($inFence)
            {
                continue;
            }

            if (!$this->Lines || preg_match($pattern, $this->NextLine))
            {
                if ($discard && $this->Lines)
                {
                    $this->getLine();
                }
                break;
            }
        }
        while ($this->Lines);

        return implode($unwrap ? " " : PHP_EOL, $lines);
    }

    private function mergeValue(?string & $ours, ?string $theirs): void
    {
        // Do nothing if there's nothing to merge
        if (is_null($theirs) || !trim($theirs))
        {
            return;
        }

        // If we have nothing to keep, use the incoming value
        if (is_null($ours) || !trim($ours))
        {
            $ours = $theirs;
            return;
        }
    }

    private function mergeLines(?array & $ours, ?array $theirs): void
    {
        // Add unique incoming lines
        array_push($ours, ...array_diff($theirs, $ours));
    }

    private function mergeType(?array & $ours, ?array $theirs): void
    {
        $this->mergeValue($ours["type"], $theirs["type"] ?? null);
        $this->mergeValue($ours["description"], $theirs["description"] ?? null);
    }

    /**
     * Add missing values from a PhpDocParser representing the same structural
     * element in a parent class or interface
     *
     */
    public function mergeInherited(PhpDocParser $parent)
    {
        $this->mergeValue($this->Summary, $parent->Summary);
        $this->mergeValue($this->Description, $parent->Description);
        $this->mergeLines($this->TagLines, $parent->TagLines);
        foreach ($parent->Tags as $tag => $content)
        {
            if (!is_array($this->Tags[$tag] ?? null))
            {
                $this->Tags[$tag] = $content;
            }
            elseif (is_array($content))
            {
                $this->mergeLines($this->Tags[$tag], $content);
            }
        }
        foreach ($parent->Params as $name => $data)
        {
            $this->mergeType($this->Params[$name], $data);
        }
        $this->mergeType($this->Return, $parent->Return);
    }

    /**
     * @param string[] $docBlocks
     */
    public static function fromDocBlocks(array $docBlocks, bool $legacyNullable = true): ?self
    {
        if (!$docBlocks)
        {
            return null;
        }
        $parser = new self(array_shift($docBlocks), $legacyNullable);
        while ($docBlocks)
        {
            $parser->mergeInherited(new self(array_shift($docBlocks), $legacyNullable));
        }
        return $parser;
    }
}
