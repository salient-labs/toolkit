<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Entity\Normalisable;
use Salient\Utility\Str;

/**
 * @api
 *
 * @phpstan-require-implements Normalisable
 */
trait NormalisableTrait
{
    /**
     * @inheritDoc
     */
    public static function normaliseProperty(
        string $name,
        bool $fromData = true,
        string ...$declaredName
    ): string {
        return Str::snake($name);
    }
}
