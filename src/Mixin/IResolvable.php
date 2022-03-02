<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

/**
 * Resolves inaccessible properties by normalising their names
 *
 * @package Lkrms
 */
interface IResolvable
{
    public function ResolvePropertyName(string $name): string;

    public function __set(string $name, $value): void;

    public function __get(string $name);

    public function __isset(string $name): bool;

    public function __unset(string $name): void;
}

