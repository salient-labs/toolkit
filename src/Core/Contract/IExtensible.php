<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

/**
 * Implements arbitrary property storage
 *
 * @package Lkrms
 * @see TExtensible
 */
interface IExtensible extends IGettable, ISettable, IResolvable
{
    public function setMetaProperty(string $name, $value): void;

    public function getMetaProperty(string $name);

    public function isMetaPropertySet(string $name): bool;

    public function unsetMetaProperty(string $name): void;

    public function getMetaProperties(): array;
}
