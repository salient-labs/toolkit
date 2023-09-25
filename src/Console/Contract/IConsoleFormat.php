<?php declare(strict_types=1);

namespace Lkrms\Console\Contract;

use Lkrms\Console\Catalog\ConsoleAttribute as Attribute;

/**
 * Applies a target-defined format to console output
 */
interface IConsoleFormat
{
    /**
     * Format a string before it is written to the target
     *
     * @param array<Attribute::*,mixed> $attributes
     */
    public function apply(?string $text, array $attributes = []): string;
}
