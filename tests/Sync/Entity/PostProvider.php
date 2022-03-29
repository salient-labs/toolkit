<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

interface PostProvider extends \Lkrms\Sync\Provider\ISyncProvider
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
     * @return Post[]
     */
    public function getPosts(): array;
}

