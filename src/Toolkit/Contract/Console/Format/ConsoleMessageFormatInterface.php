<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

interface ConsoleMessageFormatInterface
{
    /**
     * Format a message before it is written to the target
     */
    public function apply(
        string $msg1,
        ?string $msg2,
        string $prefix,
        ConsoleMessageAttributesInterface $attributes
    ): string;
}
