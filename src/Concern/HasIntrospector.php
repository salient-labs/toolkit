<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Support\ClosureBuilder;

trait HasIntrospector
{
    final protected function introspector(): ClosureBuilder
    {
        return ClosureBuilder::get(static::class);
    }
}
