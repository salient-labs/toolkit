<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

/**
 * @api
 */
interface MessageFormatInterface
{
    /**
     * Format a console message
     */
    public function apply(
        string $msg1,
        ?string $msg2,
        string $prefix,
        MessageAttributesInterface $attributes
    ): string;
}
