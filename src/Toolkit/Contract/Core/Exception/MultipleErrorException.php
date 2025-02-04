<?php declare(strict_types=1);

namespace Salient\Contract\Core\Exception;

use Salient\Contract\Console\ConsoleWriterInterface;

/**
 * @api
 */
interface MultipleErrorException extends Exception
{
    /**
     * Get the exception's message without errors
     */
    public function getMessageOnly(): string;

    /**
     * Get the exception's errors
     *
     * @return string[]
     */
    public function getErrors(): array;

    /**
     * Report the exception's errors as console messages with level ERROR or
     * higher
     */
    public function reportErrors(ConsoleWriterInterface $writer): void;

    /**
     * Check if the exception has unreported errors
     */
    public function hasUnreportedErrors(): bool;
}
