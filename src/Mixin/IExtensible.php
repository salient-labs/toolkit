<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

/**
 * Implements arbitrary property storage
 *
 * @package Lkrms
 */
interface IExtensible
{
    public function SetMetaProperty(string $name, $value): void;

    public function GetMetaProperty(string $name);

    public function IsMetaPropertySet(string $name): bool;

    public function UnsetMetaProperty(string $name): void;

    public function GetMetaProperties(): array;
}

