<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

/**
 * @api
 */
interface Normalisable
{
    /**
     * Normalise a property name
     *
     * @param bool $fromData If `true`, `$name` is from data being applied to
     * the class, otherwise it is a trusted property or value name.
     * @param string ...$declaredName The names of any declared or "magic"
     * properties after normalisation. Not given if `$fromData` is `false`.
     */
    public static function normaliseProperty(
        string $name,
        bool $fromData = true,
        string ...$declaredName
    ): string;
}
