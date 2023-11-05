<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity\Provider;

use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Tests\Sync\Entity\Task;

/**
 * Syncs Task objects with a backend
 *
 * @method Task createTask(ISyncContext $ctx, Task $task)
 * @method Task getTask(ISyncContext $ctx, int|string|null $id)
 * @method Task updateTask(ISyncContext $ctx, Task $task)
 * @method Task deleteTask(ISyncContext $ctx, Task $task)
 * @method iterable<Task> getTasks(ISyncContext $ctx)
 */
interface TaskProvider extends ISyncProvider {}
