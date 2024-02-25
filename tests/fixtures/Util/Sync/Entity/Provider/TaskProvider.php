<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity\Provider;

use Lkrms\Tests\Sync\Entity\Task;
use Salient\Sync\Contract\ISyncContext;
use Salient\Sync\Contract\ISyncProvider;

/**
 * Syncs Task objects with a backend
 *
 * @method Task createTask(ISyncContext $ctx, Task $task)
 * @method Task getTask(ISyncContext $ctx, int|string|null $id)
 * @method Task updateTask(ISyncContext $ctx, Task $task)
 * @method Task deleteTask(ISyncContext $ctx, Task $task)
 * @method iterable<Task> getTasks(ISyncContext $ctx)
 *
 * @generated
 */
interface TaskProvider extends ISyncProvider {}
