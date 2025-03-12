<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

use Salient\Contract\Console\Format\ConsoleMessageAttributesInterface as MessageAttributes;
use Salient\Contract\Console\Format\ConsoleTagAttributesInterface as TagAttributes;

interface ConsoleFormatInterface
{
    /**
     * Format text before it is written to the target
     *
     * @param MessageAttributes|TagAttributes|null $attributes
     */
    public function apply(?string $text, $attributes = null): string;
}
