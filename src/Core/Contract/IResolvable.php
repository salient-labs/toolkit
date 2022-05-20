<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

/**
 * Normalises property names
 *
 */
interface IResolvable
{
    /**
     * Return a normalised property name
     *
     * @param string $name
     * @return string
     */
    public static function normaliseProperty(string $name): string;
}
