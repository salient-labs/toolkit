<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Console\Catalog\ConsoleTag as Tag;

/**
 * Tag attributes
 */
final class ConsoleTagAttributes
{
    /**
     * Tag identifier
     *
     * @var Tag::*
     * @readonly
     */
    public int $Tag;

    /**
     * Tag as it was originally used
     *
     * @readonly
     */
    public string $OpenTag;

    /**
     * Horizontal whitespace before the tag (fenced code blocks only)
     *
     * @readonly
     */
    public ?string $Indent;

    /**
     * Fenced code block info string
     *
     * @readonly
     */
    public ?string $InfoString;

    /**
     * @param Tag::* $tag
     */
    public function __construct(
        int $tag,
        string $openTag,
        ?string $indent = null,
        ?string $infoString = null
    ) {
        $this->Tag = $tag;
        $this->OpenTag = $openTag;
        $this->Indent = $indent;
        $this->InfoString = $infoString;
    }
}
