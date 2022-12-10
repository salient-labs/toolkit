<?php declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * @template T
 */
trait HasItems
{
    /**
     * @var mixed[]
     * @psalm-var T[]
     */
    private $_Items = [];
}
