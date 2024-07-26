<?php declare(strict_types=1);

namespace Salient\Console\Contract;

use Salient\Console\Support\ConsoleMessageAttributes as MessageAttributes;
use Salient\Console\Support\ConsoleTagAttributes as TagAttributes;

interface ConsoleFormatInterface
{
    /**
     * Format text before it is written to the target
     *
     * @param MessageAttributes|TagAttributes|null $attributes
     */
    public function apply(?string $text, $attributes = null): string;
}
