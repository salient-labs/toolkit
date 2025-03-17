<?php declare(strict_types=1);

namespace Salient\Console\Format;

/**
 * @api
 */
final class NullFormat extends AbstractFormat
{
    /**
     * @inheritDoc
     */
    public function apply(string $string, $attributes = null): string
    {
        return $string;
    }
}
