<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Console\Catalog\ConsoleAttribute as Attribute;
use Lkrms\Console\Catalog\ConsoleTag as Tag;
use Lkrms\Console\Contract\IConsoleFormat as Format;
use Lkrms\Console\Support\ConsoleLoopbackFormat as LoopbackFormat;
use Lkrms\Console\Support\ConsoleManPageFormat as ManPageFormat;
use Lkrms\Console\Support\ConsoleMarkdownFormat as MarkdownFormat;

/**
 * Maps inline formatting tags to target-defined formats
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

    /**
     * Get the format assigned to a tag and use it to format a string before it
     * is written to the target
     *
     * @param Tag::* $tag
     * @param array<Attribute::*,mixed> $attributes
     */
    public function apply($tag, ?string $text, array $attributes = []): string
    {
        $attributes[Attribute::TAG_ID] = $tag;
        $format = $this->Formats[$tag] ?? $this->FallbackFormat;
        return $format->apply($text, $attributes);
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

    public static function getMarkdownFormats(): self
    {
        return (new self())
            ->set(Tag::HEADING, new MarkdownFormat('***', '***'))
            ->set(Tag::BOLD, new MarkdownFormat('**', '**'))
            ->set(Tag::ITALIC, new MarkdownFormat('*', '*'))
            ->set(Tag::UNDERLINE, new MarkdownFormat('*<u>', '</u>*'))
            ->set(Tag::LOW_PRIORITY, new MarkdownFormat('<small>', '</small>'))
            ->set(Tag::CODE_SPAN, new MarkdownFormat('`', '`'))
            ->set(Tag::CODE_BLOCK, new MarkdownFormat('```', '```'));
    }

    public static function getManPageFormats(): self
    {
        return (new self())
            ->set(Tag::HEADING, new ManPageFormat('***', '***'))
            ->set(Tag::BOLD, new ManPageFormat('**', '**'))
            ->set(Tag::ITALIC, new ManPageFormat('*', '*'))
            ->set(Tag::UNDERLINE, new ManPageFormat('*', '*'))
            ->set(Tag::LOW_PRIORITY, new ManPageFormat('', ''))
            ->set(Tag::CODE_SPAN, new ManPageFormat('`', '`'))
            ->set(Tag::CODE_BLOCK, new ManPageFormat('```', '```'));
    }
}
