<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

interface MessageFormatInterface
{
    /**
     * Format a message before it is written to the target
     */
    public function apply(
        string $msg1,
        ?string $msg2,
        string $prefix,
        MessageAttributesInterface $attributes
    ): string;
}
