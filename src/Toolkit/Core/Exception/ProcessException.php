<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Core\Process;
use Salient\Utility\Get;
use Throwable;

/**
 * @api
 */
class ProcessException extends Exception
{
    /**
     * Creates a new exception
     *
     * To get a message for the exception, `$format` and `$values` are passed to
     * {@see sprintf()} after any {@see Process} instances in `$values` are
     * replaced with the command they spawn.
     *
     * {@see sprintf()} is not called if `$values` is empty or `null`.
     *
     * @param array<Process|string|int|bool|float|null>|null $values
     */
    public function __construct(
        string $format = '',
        ?array $values = null,
        ?Throwable $previous = null,
        ?int $exitStatus = null
    ) {
        $message = $values
            ? sprintf($format, ...$this->filterValues($values))
            : $format;

        parent::__construct($message, $previous, $exitStatus);
    }

    /**
     * @param non-empty-array<Process|string|int|bool|float|null> $values
     * @return list<string|int|bool|float|null>
     */
    private function filterValues(array $values): array
    {
        foreach ($values as $value) {
            $filtered[] = $value instanceof Process
                ? Get::code($value->getCommand())
                : $value;
        }
        return $filtered;
    }
}
