<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Support\Introspector;

trait HasIntrospector
{
    final protected function introspector(): Introspector
    {
        return Introspector::get(static::class);
    }
}
