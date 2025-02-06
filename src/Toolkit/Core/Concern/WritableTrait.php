<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Entity\Writable;
use Salient\Core\Internal\WritePropertyTrait;

/**
 * @api
 *
 * @phpstan-require-implements Writable
 */
trait WritableTrait
{
    use WritePropertyTrait;

    public static function getWritableProperties(): array
    {
        return [];
    }
}
