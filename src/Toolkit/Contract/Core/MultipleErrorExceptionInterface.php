<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 */
interface MultipleErrorExceptionInterface extends ExceptionInterface
{
    /**
     * Get the original exception message
     */
    public function getMessageWithoutErrors(): string;

    /**
     * Get the exception's errors
     *
     * @return string[]
     */
    public function getErrors(): array;

    /**
     * Report the exception's errors with console message level ERROR
     *
     * @return $this
     */
    public function reportErrors();

    /**
     * Check if the exception has unreported errors
     *
     * This method should return `false` after
     * {@see MultipleErrorExceptionInterface::reportErrors()} is called.
     */
    public function hasUnreportedErrors(): bool;
}
