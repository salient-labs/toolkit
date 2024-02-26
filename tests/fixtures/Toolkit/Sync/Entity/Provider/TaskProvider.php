<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity\Provider;

use Salient\Sync\Contract\ISyncContext;
use Salient\Sync\Contract\ISyncProvider;
use Salient\Tests\Sync\Entity\Task;

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
