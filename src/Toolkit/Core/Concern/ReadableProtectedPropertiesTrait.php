<?php declare(strict_types=1);

namespace Salient\Core\Concern;

/**
 * @api
 */
trait ReadableProtectedPropertiesTrait
{
    use ReadableTrait;

    /**
     * @inheritDoc
     */
    public static function getReadableProperties(): array
    {
        return ['*'];
    }
}
