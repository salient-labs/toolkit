<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

/**
 * Stores arbitrary property values
 *
 * @package Lkrms
 */
interface IExtensible
{
    public function setMetaProperty(string $name, $value): void;

    public function getMetaProperty(string $name);

    public function isMetaPropertySet(string $name): bool;

    public function unsetMetaProperty(string $name): void;

    /**
     * Return an array that maps names to values for all stored properties
     *
     * @return array<string,mixed>
     */
    public function getMetaProperties(): array;
}
