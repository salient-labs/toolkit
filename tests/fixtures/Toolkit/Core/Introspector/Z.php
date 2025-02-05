<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Contract\Core\Entity\Treeable;
use Salient\Core\Concern\TreeableTrait;

class Z implements Treeable
{
    use TreeableTrait;

    /** @var static|null */
    public ?self $Parent;
    /** @var iterable<static> */
    public iterable $Children;

    /**
     * @inheritDoc
     */
    public static function getParentProperty(): string
    {
        return 'Parent';
    }

    /**
     * @inheritDoc
     */
    public static function getChildrenProperty(): string
    {
        return 'Children';
    }
}
