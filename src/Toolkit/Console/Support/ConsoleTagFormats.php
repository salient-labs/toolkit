<?php declare(strict_types=1);

namespace Salient\Console\Support;

use Salient\Console\Contract\ConsoleFormatInterface as Format;
use Salient\Console\Support\ConsoleTagAttributes as TagAttributes;
use Salient\Contract\Console\ConsoleTag as Tag;
use Salient\Core\Concern\HasImmutableProperties;

/**
 * Maps inline formatting tags to formats
 */
final class ConsoleTagFormats
{
    use HasImmutableProperties;

    /** @var array<Tag::*,Format> */
    private array $Formats = [];
    private bool $Unescape;
    private bool $WrapAfterApply;
    private Format $FallbackFormat;

    public function __construct(
        bool $unescape = true,
        bool $wrapAfterApply = false,
        ?Format $fallbackFormat = null
    ) {
        $this->Unescape = $unescape;
        $this->WrapAfterApply = $wrapAfterApply;
        $this->FallbackFormat = $fallbackFormat
            ?: ConsoleFormat::getDefaultFormat();
    }

    /**
     * @return static
     */
    public function withUnescape(bool $value = true)
    {
        return $this->withPropertyValue('Unescape', $value);
    }

    /**
     * @return static
     */
    public function withWrapAfterApply(bool $value = true)
    {
        return $this->withPropertyValue('WrapAfterApply', $value);
    }

    /**
     * True if text should be unescaped for the target
     */
    public function getUnescape(): bool
    {
        return $this->Unescape;
    }

    /**
     * True if text should be wrapped after formatting
     */
    public function getWrapAfterApply(): bool
    {
        return $this->WrapAfterApply;
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
