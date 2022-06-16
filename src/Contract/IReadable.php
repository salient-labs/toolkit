<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Reads properties that have not been declared or are not visible in the
 * current scope
 *
 */
interface IReadable
{
    /**
     * Return a gettable property list, or ["*"] for all available properties
     *
     * @return string[]
     */
    public static function getGettable(): array;

    public function __get(string $name);

    public function __isset(string $name): bool;
}
