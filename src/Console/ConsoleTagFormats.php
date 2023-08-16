<?php declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\Catalog\ConsoleTag as Tag;
use Lkrms\Console\Contract\IConsoleFormat as Format;
use Lkrms\Console\Support\ConsoleLoopbackFormat as LoopbackFormat;

/**
 * Maps inline formatting tags to target-defined formats
 *
 */
final class ConsoleTagFormats
{
    /**
     * @var array<Tag::*,Format>
     */
    private array $Formats = [];

    private Format $FallbackFormat;

    public function __construct(?Format $fallbackFormat = null)
    {
        $this->FallbackFormat = $fallbackFormat
            ?: ConsoleFormat::getDefaultFormat();
    }

    /**
     * Assign a format to a tag
     *
     * @param Tag::* $tag
     * @return $this
     */
    public function set($tag, Format $format)
    {
        $this->Formats[$tag] = $format;
        return $this;
    }

    /**
     * Get the format assigned to a tag
     *
     * @param Tag::* $tag
     */
    public function get($tag): Format
    {
        return $this->Formats[$tag] ?? $this->FallbackFormat;
    }

    public static function getLoopbackFormats(): self
    {
        return (new self())
            ->set(Tag::HEADING, new LoopbackFormat('***', '***'))
            ->set(Tag::BOLD, new LoopbackFormat('**', '**'))
            ->set(Tag::ITALIC, new LoopbackFormat('*', '*'))
            ->set(Tag::UNDERLINE, new LoopbackFormat('<', '>'))
            ->set(Tag::LOW_PRIORITY, new LoopbackFormat('~~', '~~'))
            ->set(Tag::CODE_SPAN, new LoopbackFormat('`', '`'))
            ->set(Tag::CODE_BLOCK, new LoopbackFormat('```', '```'));
    }
}
