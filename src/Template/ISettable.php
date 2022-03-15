<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * Provides public write access to protected properties via __set and __unset
 *
 * @package Lkrms
 * @see TSettable
 */
interface ISettable extends IAccessible
{
    public function getSettable(): ?array;

    public function __set(string $name, mixed $value): void;

    public function __unset(string $name): void;
}

