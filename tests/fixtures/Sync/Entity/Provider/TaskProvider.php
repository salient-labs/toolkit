<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity\Provider;

use Lkrms\Iterator\Contract\FluentIteratorInterface;
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
 * @method FluentIteratorInterface<array-key,Task> getTasks(ISyncContext $ctx)
 *
 * @generated by lk-util
 * @salient-generate-command generate sync provider --magic --op='create,get,update,delete,get-list' 'Lkrms\Tests\Sync\Entity\Task'
 */
interface TaskProvider extends ISyncProvider {}