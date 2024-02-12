<?php declare(strict_types=1);

namespace Salient\Core\Concern;

/**
 * Extends HasReadableProperties to read all protected properties by default
 *
 * @see HasReadableProperties
 */
trait ReadsProtectedProperties
{
    use HasReadableProperties;

    public static function getReadableProperties(): array
    {
        return ['*'];
    }
}
