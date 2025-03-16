<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Console\Format\ConsoleTagAttributes as TagAttributes;
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

    /** @var array<Format::TAG_*,Format> */
    private array $Formats = [];
    private bool $RemoveEscapes;
    private bool $WrapAfterFormatting;
    private Format $FallbackFormat;

    public function __construct(
        bool $removeEscapes = true,
        bool $wrapAfterFormatting = false,
        ?Format $fallbackFormat = null
    ) {
        $this->RemoveEscapes = $removeEscapes;
        $this->WrapAfterFormatting = $wrapAfterFormatting;
        $this->FallbackFormat = $fallbackFormat
            ?: ConsoleFormat::getDefaultFormat();
    }

    /**
     * @return static
     */
    public function withRemoveEscapes(bool $remove = true)
    {
        return $this->with('RemoveEscapes', $remove);
    }

    /**
     * @return static
     */
    public function withWrapAfterFormatting(bool $value = true)
    {
        return $this->with('WrapAfterFormatting', $value);
    }

    /**
     * True if text should be unescaped for the target
     */
    public function getRemoveEscapes(): bool
    {
        return $this->RemoveEscapes;
    }

    /**
     * True if text should be wrapped after formatting
     */
    public function getWrapAfterFormatting(): bool
    {
        return $this->WrapAfterFormatting;
    }

    /**
     * Get an instance with a format assigned to a tag
     *
     * @param Format::TAG_* $tag
     * @return static
     */
    public function withFormat($tag, Format $format)
    {
        return $this->with('Formats', Arr::set($this->Formats, $tag, $format));
    }

    /**
     * Get the format assigned to a tag
     *
     * @param Format::TAG_* $tag
     */
    public function getFormat($tag): Format
    {
        return $this->Formats[$tag] ?? $this->FallbackFormat;
    }

    /**
     * Format tagged text before it is written to the target
     */
    public function apply(?string $string, TagAttributes $attributes): string
    {
        $format = $this->Formats[$attributes->getTag()] ?? $this->FallbackFormat;
        return $format->apply($string, $attributes);
    }
}
