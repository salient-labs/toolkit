<?php declare(strict_types=1);

namespace Lkrms\Console\Contract;

use Lkrms\Console\Support\ConsoleMessageAttributes as MessageAttributes;
use Lkrms\Console\Support\ConsoleTagAttributes as TagAttributes;

interface ConsoleFormatInterface
{
    /**
     * Format text before it is written to the target
     *
     * @param MessageAttributes|TagAttributes|null $attributes
     */
    public function apply(?string $text, $attributes = null): string;
}
