<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Support\Dictionary\RegularExpression as Regex;
use UnexpectedValueException;

/**
 * Parses PSR-5 PHPDocs
 *
 * Newlines and other formatting are preserved unless otherwise noted.
 *
 * @property-read string|null $Summary
 * @property-read string|null $Description
 * @property-read string[] $TagLines
 * @property-read array<string,string[]|true> $Tags
 * @property-read array<string,array{type:string|null,description:string|null}> $Params
 * @property-read array{type:string,description:string|null}|null $Return
 * @property-read array<int,array{name:string|null,type:string,description:string|null}> $Var
 * @property-read array<string,array{type:string|null}> $Templates
 */
final class PhpDocParser implements IReadable
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

    public const TYPE_REGEX = '/^' . self::TYPE_PATTERN . '$/';

    /**
     * The summary, if provided
     *
     * Newlines are removed.
     *
     * @var string|null
     */
    protected $Summary;

    /**
     * The description, if provided
     *
     * @var string|null
     */
    protected $Description;

    /**
     * The full text of each tag, in order of appearance
     *
     * @var string[]
     */
    protected $TagLines = [];

    /**
     * Tag name => tag content, in order of appearance
     *
     * Tags that only appear by themselves (i.e. without a description or any
     * other metadata) are added with the boolean value `true`.
     *
     * @var array<string,string[]|true>
     */
    protected $Tags = [];

    /**
     * Parameter name => parameter metadata
     *
     * @var array<string,array{type:string|null,description:string|null}>
     */
    protected $Params = [];

    /**
     * Return value metadata, if provided
     *
     * @var array{type:string,description:string|null}|null
     */
    protected $Return;

    /**
     * Property or constant metadata, if provided
     *
     * @var array<int,array{name:string|null,type:string,description:string|null}>
     */
    protected $Var = [];

    /**
     * Template type => template metadata
     *
     * @var array<string,array{type:string|null}>
     */
    protected $Templates = [];

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
    public function __construct(string $docBlock, string $classDocBlock = null, bool $legacyNullable = true)
    {
        // Check for a leading "*" after every newline as per PSR-5
        if (!preg_match(Regex::delimit(Regex::PHP_DOCBLOCK), $docBlock, $matches)) {
            throw new UnexpectedValueException('Invalid DocBlock');
        }
        $this->LegacyNullable = $legacyNullable;

        // - Extract text between "/**" and "*/"
        // - Remove trailing spaces and leading "* " or "*" from each line
        // - Trim the entire PHPDoc
        // - Split into string[]
        $this->Lines = preg_split(
            '/\r?\n/u',
            trim(preg_replace('/(^\h*\* ?|\h+(?=\r?$))/um',
                              '',
                              $matches['content']))
        );
        $this->NextLine = reset($this->Lines);

        if ($this->NextLine !== false && !preg_match(self::TAG_REGEX, $this->NextLine)) {
            $this->Summary = $this->getLinesUntil('/^$/', true, true);

            if ($this->NextLine !== false && !preg_match(self::TAG_REGEX, $this->NextLine)) {
                $this->Description = rtrim($this->getLinesUntil(self::TAG_REGEX));
            }
        }

        while ($this->Lines &&
                preg_match(self::TAG_REGEX, $lines = $this->getLinesUntil(self::TAG_REGEX), $matches)) {
            $this->TagLines[] = $lines;

            $lines              = preg_replace('/^@' . preg_quote($matches['tag'], '/') . '\h*/', '', $lines);
            $tag                = ltrim($matches['tag'], '\\');
            $this->Tags[$tag][] = $lines;

            if (!$lines) {
                continue;
            }

            // - If the tag may have an implicit multi-line description, collect
            //   metadata by calling `strtok($lines, " \t")` for each value up
            //   to but NOT including the last value before the description
            //   starts, otherwise the value will not be tokenised correctly
            //   when the description starts on a new line
            // - Collect the last value by calling `strtok($lines, " \t\n\r")`
            // - Call `$this->getValue()` to finalise the tag's metadata and
            //   collect its description (if applicable)
            // - To collect metadata for a one-line tag (i.e. with no
            //   description), call `strtok($lines, " \t")` for each value
            $meta = 0;
            switch ($tag) {
                // @param [type] $<name> [description]
                case 'param':
                    $token = strtok($lines, " \t\n\r");
                    $type  = null;
                    if (!preg_match('/^\$/', $token)) {
                        $type = $token;
                        $meta++;
                        $token = strtok(" \t\n\r");
                        if ($token === false || !preg_match('/^\$/', $token)) {
                            continue 2;
                        }
                    }
                    if ($name = substr($token, 1)) {
                        $meta++;
                        $this->Params[$name] = $this->getValue($type, $lines, $meta);
                    }
                    break;

                // @return <type> [description]
                case 'return':
                    $token = strtok($lines, " \t\n\r");
                    $type  = $token;
                    $meta++;
                    $this->Return = $this->getValue($type, $lines, $meta);
                    break;

                // @var [type] [$<name>] [description]
                case 'var':
                    unset($name);
                    $token = strtok($lines, " \t\n\r");
                    $type  = $token;
                    $meta++;
                    $token = strtok(" \t");
                    if ($token !== false && preg_match('/^\$/', $token)) {
                        $name = $token;
                        $meta++;
                    }
                    $var = $this->getValue($type, $lines, $meta, true, ['name' => $name ?? null]);
                    if ($var['description'] && $this->Summary) {
                        $this->Description .= ($this->Description ? str_repeat(PHP_EOL, 2) : '') . $var['description'];
                        $var['description'] = $this->Summary;
                    } else {
                        $var['description'] = $var['description'] ?: $this->Summary;
                    }
                    $this->Var[] = $var;
                    break;

                // @template <name> [of <type>]
                // @template-covariant <name> [of <type>]
                case 'template':
                case 'template-covariant':
                    $token = strtok($lines, " \t");
                    $name  = $token;
                    $meta++;
                    $token = strtok(" \t");
                    $type  = 'mixed';
                    if ($token === 'of') {
                        $meta++;
                        $token = strtok(" \t");
                        if ($token !== false) {
                            $meta++;
                            $type = $token;
                        }
                    }
                    $this->Templates[$name] = $this->getValue($type, $lines, $meta, false);
                    break;
            }
        }

        // Release strtok's copy of the string most recently passed to it
        strtok('', '');

        // Replace tags that have no content with `true`
        $this->Tags = array_map(
            fn(array $tag) => count(array_filter($tag)) ? $tag : true,
            $this->Tags
        );

        // Merge @template types from the declaring class, if available
        if ($classDocBlock) {
            $class = new self($classDocBlock, null, $legacyNullable);
            foreach ($class->Templates as $template => $data) {
                $this->mergeType($this->Templates[$template], $data, false);
            }
        }
    }

    private function getValue(?string $type, string $lines, int $meta, bool $withDesc = true, array $initial = []): array
    {
        if ($this->LegacyNullable && !is_null($type)) {
            $nullable = preg_replace('/^null\||\|null$/', '', $type, 1, $count);
            if ($count && preg_match(self::TYPE_REGEX, $nullable)) {
                $type = "?$nullable";
            }
        }
        $initial += ['type' => $type];
        if ($withDesc) {
            return $initial + ['description' => preg_split('/\s+/', $lines, $meta + 1)[$meta] ?? null];
        }

        return $initial;
    }

    /**
     * @phpstan-impure
     */
    private function getLine(): string
    {
        $line           = array_shift($this->Lines);
        $this->NextLine = reset($this->Lines);

        return $line;
    }

    /**
     * @phpstan-impure
     */
    private function getLinesUntil(string $pattern, bool $discard = false, bool $unwrap = false): string
    {
        $lines   = [];
        $inFence = false;

        do {
            $lines[] = $line = $this->getLine();

            if ((!$inFence && preg_match('/^```+/', $line, $fence)) ||
                    ($inFence && $line == ($fence[0] ?? null))) {
                $inFence = !$inFence;
            }
            if ($inFence) {
                continue;
            }

            if (!$this->Lines) {
                break;
            }
            if (!preg_match($pattern, $this->NextLine)) {
                continue;
            }
            if ($discard) {
                do {
                    $this->getLine();
                } while ($this->Lines && preg_match($pattern, $this->NextLine));
            }
            break;
        } while ($this->Lines);

        return implode($unwrap ? ' ' : PHP_EOL, $lines);
    }

    public function unwrap(?string $value): ?string
    {
        return is_null($value) ? null : str_replace(PHP_EOL, ' ', $value);
    }

    /**
     * True if the PHPDoc contains more than a summary and/or variable type
     * information
     *
     */
    public function hasDetail(): bool
    {
        if ($this->Description) {
            return true;
        }
        foreach ([...$this->Params, $this->Return, ...$this->Var] as $entity) {
            if (($description = $entity['description'] ?? null) && $description !== $this->Summary) {
                return true;
            }
        }
        if (array_diff_key($this->Tags, array_flip(['param', 'return', 'var', 'internal']))) {
            return true;
        }

        return false;
    }

    private function mergeValue(?string &$ours, ?string $theirs): void
    {
        // Do nothing if there's nothing to merge
        if (is_null($theirs) || !trim($theirs)) {
            return;
        }

        // If we have nothing to keep, use the incoming value
        if (is_null($ours) || !trim($ours)) {
            $ours = $theirs;

            return;
        }
    }

    private function mergeLines(?array &$ours, ?array $theirs): void
    {
        // Add unique incoming lines
        array_push($ours, ...array_diff($theirs, $ours));
    }

    private function mergeType(?array &$ours, ?array $theirs, bool $withDesc = true): void
    {
        $this->mergeValue($ours['type'], $theirs['type'] ?? null);
        if ($withDesc) {
            $this->mergeValue($ours['description'], $theirs['description'] ?? null);
        }
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
        foreach ($parent->Tags as $tag => $content) {
            if (!is_array($this->Tags[$tag] ?? null)) {
                $this->Tags[$tag] = $content;
            } elseif (is_array($content)) {
                $this->mergeLines($this->Tags[$tag], $content);
            }
        }
        foreach ($parent->Params as $name => $data) {
            $this->mergeType($this->Params[$name], $data);
        }
        $this->mergeType($this->Return, $parent->Return);
    }

    /**
     * @param string[] $docBlocks
     * @param array<string|null>|null $classDocBlocks
     */
    public static function fromDocBlocks(array $docBlocks, ?array $classDocBlocks = null, bool $legacyNullable = true): ?self
    {
        if (!$docBlocks) {
            return null;
        }
        $parser = new self(
            array_shift($docBlocks),
            $classDocBlocks
                ? array_shift($classDocBlocks)
                : null,
            $legacyNullable
        );
        while ($docBlocks) {
            $parser->mergeInherited(
                new self(
                    array_shift($docBlocks),
                    $classDocBlocks
                        ? array_shift($classDocBlocks)
                        : null,
                    $legacyNullable
                )
            );
        }

        return $parser;
    }
}
