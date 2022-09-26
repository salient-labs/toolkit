<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

/**
 * Syncs Post objects with a backend
 *
 * @method Post createPost(Post $post)
 * @method Post getPost(int|string $id)
 * @method Post updatePost(Post $post)
 * @method Post|null deletePost(Post $post)
 * @method iterable<Post> getPosts()
 *
 * @lkrms-generate-command lk-util generate sync provider --class='Lkrms\Tests\Sync\Entity\Post' --op='create,get,update,delete,get-list'
 */
interface PostProvider extends \Lkrms\Sync\Contract\ISyncProvider
{
}
