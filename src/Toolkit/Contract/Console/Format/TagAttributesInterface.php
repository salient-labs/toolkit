<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

use Salient\Contract\Core\Immutable;

/**
 * @api
 */
interface TagAttributesInterface extends
    Immutable,
    HasTag
{
    /**
     * Get the tag
     *
     * @return TagAttributesInterface::TAG_*
     */
    public function getTag(): int;

    /**
     * Get the tag as it originally appeared in the string
     */
    public function getOpenTag(): string;

    /**
     * Get the depth of the tag
     */
    public function getDepth(): int;

    /**
     * Check if the tag has nested tags
     */
    public function hasChildren(): bool;

    /**
     * Get horizontal whitespace before the tag, or null if the tag is not a
     * fenced code block
     */
    public function getIndent(): ?string;

    /**
     * Get the tag's info string, or null if the tag is not a fenced code block
     */
    public function getInfoString(): ?string;
}
