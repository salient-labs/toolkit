<?php declare(strict_types=1);

namespace Lkrms\Sync\Concern;

use Lkrms\Sync\Support\SyncIntrospector;

trait HasSyncIntrospector
{
    final protected function introspector(): SyncIntrospector
    {
        return SyncIntrospector::get(static::class);
    }
}
