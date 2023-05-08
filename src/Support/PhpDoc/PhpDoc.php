<?php declare(strict_types=1);

namespace Lkrms\Support\PhpDoc;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Convert;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use UnexpectedValueException;

/**
 * Parses PSR-5 PHPDocs
 *
 * Newlines are preserved and Markdown code fences are honoured unless otherwise
 * noted.
 *
 * @property-read string|null $Summary
 * @property-read string|null $Description
 * @property-read string[] $Tags
 * @property-read array<string,string[]|true> $TagsByName
 * @property-read array<string,PhpDocParamTag> $Params
 * @property-read PhpDocReturnTag|null $Return
 * @property-read PhpDocVarTag[] $Vars
 * @property-read array<string,PhpDocTemplateTag> $Templates
 */
final class PhpDoc implements IReadable
{
    use TFullyReadable;

    /**
     * The summary
     *
     * Newlines are removed and Markdown code fences are not honoured.
     *
     * @var string|null
     */
    protected $Summary;

    /**
     * The description
     *
     * @var string|null
     */
    protected $Description;

    /**
     * The full text of each tag, in order of appearance
     *
     * @var string[]
     */
    protected $Tags = [];

    /**
     * [ Tag name => content, in order of appearance ]
     *
     * Text between a tag's name and the next tag is regarded as the tag's
     * content.
     *
     * If no content is associated with a tag, its content array is replaced
     * with the boolean value `true`.
     *
     * @var array<string,string[]|true>
     */
    protected $TagsByName = [];

    /**
     * [ Parameter name => parameter metadata ]
     *
     * @var array<string,PhpDocParamTag>
     */
    protected $Params = [];

    /**
     * Return value metadata
     *
     * @var PhpDocReturnTag|null
     */
    protected $Return;

    /**
     * Property or constant metadata
     *
     * May be given more than once per PHPDoc, e.g. when documenting multiple
     * properties declared in one statement.
     *
     * @var PhpDocVarTag[]
     */
    protected $Vars = [];

    /**
     * [ Template name => template metadata ]
     *
     * @var array<string,PhpDocTemplateTag>
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
     * @param bool $legacyNullable If `true`, convert `<type>|null` and
     * `null|<type>` to `?<type>`.
     */
    public function __construct(
        string $docBlock,
        string $classDocBlock = null,
        bool $legacyNullable = true
    ) {
        // Check for a leading "*" after every newline as per PSR-5
        if (!preg_match(Regex::delimit(Regex::PHP_DOCBLOCK), $docBlock, $matches)) {
            throw new UnexpectedValueException('Invalid DocBlock');
        }
        $this->LegacyNullable = $legacyNullable;

        // 5. Split into string[]
        $this->Lines = explode(
            "\n",
            // 4. Trim the entire PHPDoc
            trim(
                // 3. Remove trailing spaces and leading "* " or "*"
                preg_replace(
                    '/(^\h*\* ?|\h+$)/um',
                    '',
                    // 2. Normalise line endings
                    Convert::lineEndingsToUnix(
                        // 1. Extract text between "/**" and "*/"
                        $matches['content']
                    )
                )
            )
        );
        $this->NextLine = reset($this->Lines);

        $tagRegex = Regex::delimit('^' . Regex::PHPDOC_TAG);
        if ($this->NextLine !== false && !preg_match($tagRegex, $this->NextLine)) {
            $this->Summary = $this->getLinesUntil('/^$/', true, true);

            if ($this->NextLine !== false && !preg_match($tagRegex, $this->NextLine)) {
                $this->Description = rtrim($this->getLinesUntil($tagRegex));
            }
        }

        while ($this->Lines && preg_match(
            $tagRegex, $text = $this->getLinesUntil($tagRegex), $matches
        )) {
            $this->Tags[] = $text;

            // Remove the tag name and any subsequent whitespace
            $text = preg_replace('/^@' . preg_quote($matches['tag'], '/') . '\s*/', '', $text);
            $tag = ltrim($matches['tag'], '\\');
            $this->TagsByName[$tag][] = $text;

            if (!$text) {
                continue;
            }

            // Use `strtok(" \t\n\r")` to extract metadata that may be followed
            // by a multi-line description, otherwise the first word of any
            // descriptions that start on the next line will be extracted too
            $metaCount = 0;
            switch ($tag) {
                // @param [type] $<name> [description]
                case 'param':
                    $token = strtok($text, " \t\n\r");
                    $type = null;
                    if (($token[0] ?? null) !== '$') {
                        $type = $token;
                        $metaCount++;
                        $token = strtok(" \t\n\r");
                        if ($token === false || ($token[0] ?? null) !== '$') {
                            /**
                             * @todo Report an invalid tag here
                             */
                            continue 2;
                        }
                    }
                    if ($name = substr($token, 1)) {
                        $metaCount++;
                        $this->Params[$name] = new PhpDocParamTag(
                            $name,
                            $type,
                            $this->getTagDescription($text, $metaCount),
                            $this->LegacyNullable
                        );
                    }
                    break;

                // @return <type> [description]
                case 'return':
                    $token = strtok($text, " \t\n\r");
                    $type = $token;
                    $metaCount++;
                    $this->Return = new PhpDocReturnTag(
                        $type,
                        $this->getTagDescription($text, $metaCount),
                        $this->LegacyNullable
                    );
                    break;

                // @var [type] [$<name>] [description]
                case 'var':
                    unset($name);
                    // Assume the first token is a type
                    $token = strtok($text, " \t\n\r");
                    $type = $token;
                    $metaCount++;
                    $token = strtok(" \t");
                    // Also assume that if a name is given, it's for a variable
                    // and not a constant
                    if ($token !== false && ($token[0] ?? null) === '$') {
                        $name = $token;
                        $metaCount++;
                    }

                    /**
                     * @todo Use optional context information from the caller
                     * (via a `ReflectionProperty|ReflectionClassConstant`
                     * parameter, perhaps?) to resolve entity names
                     */
                    $var = new PhpDocVarTag(
                        $type,
                        $name ?? null,
                        $this->getTagDescription($text, $metaCount),
                        $this->LegacyNullable
                    );
                    if ($name ?? null) {
                        $this->Vars[$name] = $var;
                    } else {
                        $this->Vars[] = $var;
                    }
                    break;

                // @template <name> [of <type>]
                case 'template':
                // @template-covariant <name> [of <type>]
                case 'template-covariant':
                    $token = strtok($text, " \t");
                    $name = $token;
                    $metaCount++;
                    $token = strtok(" \t");
                    $type = 'mixed';
                    if ($token === 'of') {
                        $metaCount++;
                        $token = strtok(" \t");
                        if ($token !== false) {
                            $metaCount++;
                            $type = $token;
                        }
                    }
                    $this->Templates[$name] = new PhpDocTemplateTag(
                        $name,
                        $type,
                        $this->LegacyNullable
                    );
                    break;
            }
        }

        // Release strtok's copy of the string most recently passed to it
        strtok('', '');

        // Rearrange this:
        //
        //     /**
        //      * Summary
        //      *
        //      * @var int Description.
        //      */
        //
        // Like this:
        //
        //     /**
        //      * Summary
        //      *
        //      * Description.
        //      *
        //      * @var int
        //      */
        //
        if (count($this->Vars) === 1) {
            $var = reset($this->Vars);
            if ($var->Description) {
                if (!$this->Summary) {
                    $this->Summary = $var->Description;
                } elseif ($this->Summary !== $var->Description) {
                    $this->Description .=
                        ($this->Description ? "\n\n" : '')
                            . $var->Description;
                }
                $var->Description = null;
            }
        }

        // Replace tags that have no content with `true`
        $this->TagsByName = array_map(
            fn(array $tag) => count(array_filter($tag)) ? $tag : true,
            $this->TagsByName
        );

        // Merge @template types from the declaring class, if available
        if ($classDocBlock) {
            $class = new self($classDocBlock, null, $legacyNullable);
            foreach ($class->Templates as $name => $tag) {
                $tag->IsClassTemplate = true;
                $this->mergeValue($this->Templates[$name], $tag);
            }
        }
    }

    /**
     * Extract a description from $text after removing $metaCount values
     *
     */
    private function getTagDescription(string $text, int $metaCount): ?string
    {
        return preg_split('/\s+/', $text, $metaCount + 1)[$metaCount] ?? null;
    }

    /**
     * Shift the next line off the beginning of $this->Lines and return it after
     * assigning its successor to $this->NextLine
     *
     * @phpstan-impure
     */
    private function getLine(): string
    {
        $line = array_shift($this->Lines);
        $this->NextLine = reset($this->Lines);

        return $line;
    }

    /**
     * Collect and implode $this->NextLine and subsequent lines until, but not
     * including, the next line that matches $pattern
     *
     * If `$unwrap` is `false`, `$pattern` is ignored between code fences, which
     * start and end when a line contains 3 or more backticks or tildes and no
     * other text aside from an optional info string after the opening fence.
     *
     * @param bool $discard If `true`, lines matching `$pattern` are discarded,
     * otherwise they are left in {@see $this->Lines}.
     * @param bool $unwrap If `true`, lines are joined with " " instead of "\n".
     *
     * @phpstan-impure
     */
    private function getLinesUntil(
        string $pattern,
        bool $discard = false,
        bool $unwrap = false
    ): string {
        $lines = [];
        $inFence = false;

        do {
            $lines[] = $line = $this->getLine();

            if (!$unwrap) {
                if ((!$inFence && preg_match('/^(```+|~~~+)/', $line, $fence)) ||
                        ($inFence && $line == ($fence[0] ?? null))) {
                    $inFence = !$inFence;
                }
                if ($inFence) {
                    continue;
                }
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

        if ($inFence) {
            throw new UnexpectedValueException('Unterminated code fence in DocBlock');
        }

        return implode($unwrap ? ' ' : "\n", $lines);
    }

    public function unwrap(?string $value): ?string
    {
        return is_null($value) ? null : str_replace("\n", ' ', $value);
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
        foreach ([...$this->Params, $this->Return, ...$this->Vars] as $entity) {
            if (($entity->Description ?? null) && $entity->Description !== $this->Summary) {
                return true;
            }
        }
        if (array_diff_key($this->TagsByName, array_flip(['param', 'return', 'var', 'template', 'internal']))) {
            return true;
        }

        return false;
    }

    /**
     * @internal
     * @param PhpDocTag|string|null $ours
     * @param PhpDocTag|string|null $theirs
     */
    public static function mergeValue(&$ours, $theirs): void
    {
        // Do nothing if there's nothing to merge
        if ($theirs === null) {
            return;
        }

        // If we have nothing to keep, use the incoming value
        if ($ours === null) {
            $ours = $theirs;
        }
    }

    /**
     * @param string[] $ours
     * @param string[] $theirs
     */
    private function mergeStrings(array &$ours, array $theirs): void
    {
        // Add unique incoming lines
        array_push($ours, ...array_diff($theirs, $ours));
    }

    private function mergeTag(?PhpDocTag &$ours, ?PhpDocTag $theirs): void
    {
        if ($theirs === null) {
            return;
        }

        if ($ours === null) {
            $ours = clone $theirs;
            return;
        }

        $ours->mergeInherited($theirs);
    }

    /**
     * Add missing values from a PhpDocParser that represents the same
     * structural element in a parent class or interface
     *
     */
    public function mergeInherited(PhpDoc $parent): void
    {
        $this->mergeValue($this->Summary, $parent->Summary);
        $this->mergeValue($this->Description, $parent->Description);
        $this->mergeStrings($this->Tags, $parent->Tags);
        foreach ($parent->TagsByName as $name => $tags) {
            // Whether $this->TagsByName[$name] is unset or boolean, assigning
            // $tags won't result in loss of information
            if (!is_array($this->TagsByName[$name] ?? null)) {
                $this->TagsByName[$name] = $tags;
            } elseif (is_array($tags)) {
                $this->mergeStrings($this->TagsByName[$name], $tags);
            }
        }
        foreach ($parent->Params as $name => $theirs) {
            $this->mergeTag($this->Params[$name], $theirs);
        }
        $this->mergeTag($this->Return, $parent->Return);
    }

    /**
     * @param string[] $docBlocks
     * @param array<string|null>|null $classDocBlocks
     */
    public static function fromDocBlocks(
        array $docBlocks,
        ?array $classDocBlocks = null,
        bool $legacyNullable = true
    ): ?self {
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
