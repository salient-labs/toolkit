<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\FormatInterface as Format;
use Salient\Contract\Console\Format\TagAttributesInterface;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Arr;

/**
 * @api
 */
class TagFormats implements Immutable
{
    use ImmutableTrait;

    /** @var array<Format::TAG_*,Format> */
    private array $Formats = [];
    private bool $RemoveEscapes;
    private bool $WrapAfterFormatting;
    private NullFormat $FallbackFormat;

    /**
     * @api
     */
    public function __construct(
        bool $removeEscapes = true,
        bool $wrapAfterFormatting = false
    ) {
        $this->RemoveEscapes = $removeEscapes;
        $this->WrapAfterFormatting = $wrapAfterFormatting;
        $this->FallbackFormat = new NullFormat();
    }

    /**
     * Get an instance where escapes are removed from strings
     *
     * @return static
     */
    public function withRemoveEscapes(bool $remove = true)
    {
        return $this->with('RemoveEscapes', $remove);
    }

    /**
     * Get an instance where strings are wrapped after formatting
     *
     * @return static
     */
    public function withWrapAfterFormatting(bool $value = true)
    {
        return $this->with('WrapAfterFormatting', $value);
    }

    /**
     * Check if escapes should be removed from strings
     */
    public function removesEscapes(): bool
    {
        return $this->RemoveEscapes;
    }

    /**
     * Check if strings should be wrapped after formatting
     */
    public function wrapsAfterFormatting(): bool
    {
        return $this->WrapAfterFormatting;
    }

    /**
     * Get an instance where a format is assigned to a tag
     *
     * @param Format::TAG_* $tag
     * @return static
     */
    public function withFormat(int $tag, Format $format)
    {
        return $this->with('Formats', Arr::set($this->Formats, $tag, $format));
    }

    /**
     * Get the format assigned to a tag
     *
     * @param Format::TAG_* $tag
     */
    public function getFormat(int $tag): Format
    {
        return $this->Formats[$tag] ?? $this->FallbackFormat;
    }

    /**
     * @internal
     */
    public function apply(
        string $string,
        TagAttributesInterface $attributes
    ): string {
        $tag = $attributes->getTag();
        $format = $this->Formats[$tag] ?? $this->FallbackFormat;
        return $format->apply($string, $attributes);
    }
}
