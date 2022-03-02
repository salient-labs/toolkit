<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

/**
 * Implements arbitrary property storage
 *
 * @package Lkrms
 * @see TExtensible
 */
interface IExtensible
{
    public function SetMetaProperty(string $name, $value): void;

    public function GetMetaProperty(string $name);

    public function IsMetaPropertySet(string $name): bool;

    public function UnsetMetaProperty(string $name): void;

    public function GetMetaProperties(): array;

    public function __set(string $name, mixed $value): void;

    public function __get(string $name): mixed;

    public function __isset(string $name): bool;

    public function __unset(string $name): void;
}

