<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity\Provider;

use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Tests\Sync\Entity\Post;

/**
 * Syncs Post objects with a backend
 *
 * @method Post createPost(SyncContext $ctx, Post $post)
 * @method Post getPost(SyncContext $ctx, int|string|null $id)
 * @method Post updatePost(SyncContext $ctx, Post $post)
 * @method Post deletePost(SyncContext $ctx, Post $post)
 * @method iterable<Post> getPosts(SyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --magic --op='create,get,update,delete,get-list' 'Lkrms\Tests\Sync\Entity\Post'
 */
interface PostProvider extends ISyncProvider
{
}
