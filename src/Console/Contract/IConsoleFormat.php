<?php declare(strict_types=1);

namespace Lkrms\Console\Contract;

/**
 * Applies a target-defined format to console output
 *
 */
interface IConsoleFormat
{
    /**
     * Format a string before it is written to the target
     *
     * @param array<string,mixed> $attributes
     */
    public function apply(?string $text, ?string $tag = null, array $attributes = []): string;
}
