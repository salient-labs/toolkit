<?php declare(strict_types=1);

namespace Lkrms\Exception;

use RuntimeException;
use Throwable;

/**
 * Base class for exceptions
 *
 */
abstract class Exception extends RuntimeException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Return an array that maps section names to content
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
        $detail = $this->getDetail();

        return parent::__toString() . implode('', array_map(
            fn(string $key, string $value) => "\n\n$key:\n$value",
            array_keys($detail),
            $detail
        ));
    }
}
