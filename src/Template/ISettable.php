<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * Provides access to protected properties via __set and __unset
 *
 * @package Lkrms
 * @see TSettable
 */
interface ISettable extends IAccessible
{
    public static function getSettable(): array;

    public function __set(string $name, $value): void;

    public function __unset(string $name): void;
}
