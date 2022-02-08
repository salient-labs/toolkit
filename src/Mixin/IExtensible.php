<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

/**
 * Used alongside TExtensible to implement arbitrary property storage
 *
 * @package Lkrms
 * @see TExtensible
 * @see TConstructible
 */
interface IExtensible
{
    public function SetMetaProperty(string $name, $value): void;

    public function GetMetaProperty(string $name);

    public function IsMetaPropertySet(string $name): bool;

    public function UnsetMetaProperty(string $name): void;
}

