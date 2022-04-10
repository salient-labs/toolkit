<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * Can be instantiated from an associative array
 *
 * @package Lkrms
 * @see TConstructible
 */
interface IConstructible
{
    /**
     *
     * @param array<string,mixed> $array
     * @return static
     */
    public static function from(array $array);

    /**
     *
     * @param array<int,array<string,mixed>> $arrays
     * @return static[]
     */
    public static function listFrom(array $arrays): array;
}

