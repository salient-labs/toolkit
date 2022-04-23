<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

/**
 * Reads inaccessible properties
 *
 * @package Lkrms
 */
interface IGettable
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
