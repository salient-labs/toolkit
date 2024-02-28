<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity\Provider;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Tests\Sync\Entity\Post;

/**
 * Syncs Post objects with a backend
 *
 * @method Post createPost(SyncContextInterface $ctx, Post $post)
 * @method Post getPost(SyncContextInterface $ctx, int|string|null $id)
 * @method Post updatePost(SyncContextInterface $ctx, Post $post)
 * @method Post deletePost(SyncContextInterface $ctx, Post $post)
 * @method iterable<Post> getPosts(SyncContextInterface $ctx)
 *
 * @generated
 */
interface PostProvider extends SyncProviderInterface {}
