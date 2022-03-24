<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * Provides public read access to protected properties via __get and __isset
 *
 * @package Lkrms
 * @see TGettable
 */
interface IGettable extends IAccessible
{
    public function getGettable(): ?array;

    public function __get(string $name);

    public function __isset(string $name): bool;
}

