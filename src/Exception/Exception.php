<?php declare(strict_types=1);

namespace Lkrms\Exception;

use RuntimeException;
use Throwable;

/**
 * Base class for exceptions
 */
abstract class Exception extends RuntimeException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get an array that maps section names to content
     *
     * See {@see \Lkrms\Curler\Exception\CurlerException} for an example.
     *
     * @return array<string,string>
     */
    public function getDetail(): array
    {
        return [];
    }

    public function __toString(): string
    {
        $detail = '';
        foreach ($this->getDetail() as $key => $value) {
            $detail .= sprintf("\n\n%s:\n%s", $key, $value);
        }

        return parent::__toString() . $detail;
    }
}
