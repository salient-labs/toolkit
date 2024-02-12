<?php declare(strict_types=1);

namespace Salient\Core\Concern;

/**
 * Extends HasWritableProperties to write all protected properties by default
 *
 * @see HasWritableProperties
 */
trait WritesProtectedProperties
{
    use HasWritableProperties;

    public static function getWritableProperties(): array
    {
        return ['*'];
    }
}
