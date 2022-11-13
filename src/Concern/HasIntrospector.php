<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Support\ClosureBuilder;

trait HasIntrospector
{
    /**
     * @var ClosureBuilder|null
     */
    private $_Introspector;

    final protected function introspector(): ClosureBuilder
    {
        return (($this->_Introspector ?? null)
            ?: ($this->_Introspector = ClosureBuilder::get(static::class)));
    }
}
