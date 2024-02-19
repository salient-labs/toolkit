<?php declare(strict_types=1);

namespace Lkrms\Exception\Contract;

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
     * True if the exception's errors have not been reported
     *
     * This method should return `false` if {@see reportErrors()} has been
     * called.
     */
    public function hasUnreportedErrors(): bool;
}
