<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity\Provider;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Tests\Sync\Entity\Task;

/**
 * Syncs Task objects with a backend
 *
 * @method Task createTask(SyncContextInterface $ctx, Task $task)
 * @method Task getTask(SyncContextInterface $ctx, int|string|null $id)
 * @method Task updateTask(SyncContextInterface $ctx, Task $task)
 * @method Task deleteTask(SyncContextInterface $ctx, Task $task)
 * @method iterable<Task> getTasks(SyncContextInterface $ctx)
 *
 * @generated
 */
interface TaskProvider extends SyncProviderInterface {}
