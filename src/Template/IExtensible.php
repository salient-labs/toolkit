<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * Implements arbitrary property storage
 *
 * @package Lkrms
 * @see TExtensible
 */
interface IExtensible
{
    public function setMetaProperty(string $name, $value): void;

    public function getMetaProperty(string $name);

    public function isMetaPropertySet(string $name): bool;

    public function unsetMetaProperty(string $name): void;

    public function getMetaProperties(): array;

    public function __set(string $name, $value): void;

    public function __get(string $name);

    public function __isset(string $name): bool;

    public function __unset(string $name): void;
}

