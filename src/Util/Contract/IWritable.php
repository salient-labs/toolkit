<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Writes properties that have not been declared or are not visible in the
 * current scope
 */
interface IWritable
{
    /**
     * Get a list of writable properties, or ["*"] for all available properties
     *
     * @return string[]
     */
    public static function getWritable(): array;

    /**
     * @param mixed $value
     */
    public function __set(string $name, $value): void;

    public function __unset(string $name): void;
}
