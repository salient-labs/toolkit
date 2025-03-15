<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Console\Format\ConsoleTagAttributes as TagAttributes;
use Salient\Contract\Console\Format\ConsoleTag as Tag;
use Salient\Contract\Console\Format\FormatInterface as Format;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Arr;

/**
 * Maps inline formatting tags to formats
 */
final class ConsoleTagFormats implements Immutable
{
    use ImmutableTrait;

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
        return $this->with('Unescape', $value);
    }

    /**
     * @return static
     */
    public function withWrapAfterApply(bool $value = true)
    {
        return $this->with('WrapAfterApply', $value);
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
     * Get an instance with a format assigned to a tag
     *
     * @param Tag::* $tag
     * @return static
     */
    public function withFormat($tag, Format $format)
    {
        return $this->with('Formats', Arr::set($this->Formats, $tag, $format));
    }

    /**
     * Get the format assigned to a tag
     *
     * @param Tag::* $tag
     */
    public function getFormat($tag): Format
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
