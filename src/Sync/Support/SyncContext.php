<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Support\ProvidableContext;
use Lkrms\Sync\Contract\ISyncContext;

/**
 * The context within which a SyncEntity is instantiated
 *
 */
final class SyncContext extends ProvidableContext implements ISyncContext
{
}
