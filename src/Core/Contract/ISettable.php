<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

/**
 * Writes inaccessible properties
 *
 * @package Lkrms
 */
interface ISettable
{
    /**
     * Return a settable property list, or ["*"] for all available properties
     *
     * @return string[]
     */
    public static function getSettable(): array;

    public function __set(string $name, $value): void;

    public function __unset(string $name): void;
}
