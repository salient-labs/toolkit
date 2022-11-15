<?php

declare(strict_types=1);

namespace Lkrms\Sync\Concern;

use Lkrms\Sync\Support\SyncClosureBuilder;

trait HasSyncIntrospector
{
    final protected function introspector(): SyncClosureBuilder
    {
        return SyncClosureBuilder::get(static::class);
    }
}
