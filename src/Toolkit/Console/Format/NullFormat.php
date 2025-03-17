<?php declare(strict_types=1);

namespace Salient\Console\Format;

/**
 * @api
 */
final class NullFormat extends AbstractFormat
{
    use IndentCodeBlockTrait;

    /**
     * @inheritDoc
     */
    public function apply(string $string, $attributes = null): string
    {
        if ($string === '') {
            return '';
        }

        return $this->indentCodeBlock($string, $attributes);
    }
}
