<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Entity\Readable;
use Salient\Core\Internal\ReadPropertyTrait;

/**
 * @api
 *
 * @phpstan-require-implements Readable
 */
trait ReadableTrait
{
    use ReadPropertyTrait;

    public static function getReadableProperties(): array
    {
        return [];
    }
}
