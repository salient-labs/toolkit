<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Support\IntrospectionClass;
use Lkrms\Support\Introspector;

trait HasIntrospector
{
    /**
     * @return Introspector<static,IntrospectionClass<static>>
     */
    final protected function introspector(): Introspector
    {
        return Introspector::get(static::class);
    }
}
