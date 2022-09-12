<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

/**
 * Syncs Post objects with a backend
 *
 * @lkrms-generate-command lk-util generate sync provider --class='Lkrms\Tests\Sync\Entity\Post' --op='create,get,update,delete,get-list'
 */
interface PostProvider extends \Lkrms\Sync\Contract\ISyncProvider
{
    /**
     * @param Post $post
     * @return Post
     */
    public function createPost(Post $post): Post;

    /**
     * @param int|string $id
     * @return Post
     */
    public function getPost($id): Post;

    /**
     * @param Post $post
     * @return Post
     */
    public function updatePost(Post $post): Post;

    /**
     * @param Post $post
     * @return null|Post
     */
    public function deletePost(Post $post): ?Post;

    /**
     * @return iterable<Post>
     */
    public function getPosts(): iterable;

}
