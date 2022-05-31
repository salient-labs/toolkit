<?php

declare(strict_types=1);

namespace Lkrms\Exception;

use RuntimeException;

/**
 * Base class for exceptions
 *
 */
class Exception extends RuntimeException
{
    /**
     * Return an array that maps section names to content
     *
     * See {@see CurlerException} for an example.
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
        return parent::__toString() . implode("", array_map(
            fn(string $key, string $value) => "\n\n$key:\n$value",
            array_keys($detail),
            $detail
        ));
    }
}
