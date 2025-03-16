<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

/**
 * @api
 */
interface FormatInterface extends HasTag
{
    /**
     * Format a string
     *
     * @param TagAttributesInterface|MessageAttributesInterface|null $attributes
     */
    public function apply(string $string, $attributes = null): string;
}
