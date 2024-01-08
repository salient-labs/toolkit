<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Console\Catalog\ConsoleTag as Tag;
use Lkrms\Console\Contract\ConsoleFormatInterface as Format;
use Lkrms\Console\Support\ConsoleTagAttributes as TagAttributes;

/**
 * Maps inline formatting tags to formats
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
     * Format tagged text before it is written to the target
     */
    public function apply(?string $text, TagAttributes $attributes): string
    {
        $format = $this->Formats[$attributes->Tag] ?? $this->FallbackFormat;
        return $format->apply($text, $attributes);
    }
}
