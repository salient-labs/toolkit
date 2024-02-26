<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity\Provider;

use Salient\Sync\Contract\ISyncContext;
use Salient\Sync\Contract\ISyncProvider;
use Salient\Tests\Sync\Entity\Post;

/**
 * Syncs Post objects with a backend
 *
 * @method Post createPost(ISyncContext $ctx, Post $post)
 * @method Post getPost(ISyncContext $ctx, int|string|null $id)
 * @method Post updatePost(ISyncContext $ctx, Post $post)
 * @method Post deletePost(ISyncContext $ctx, Post $post)
 * @method iterable<Post> getPosts(ISyncContext $ctx)
 *
 * @generated
 */
interface PostProvider extends ISyncProvider {}
