<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity\Provider;

use Salient\Sync\Contract\SyncContextInterface;
use Salient\Sync\Contract\SyncProviderInterface;
use Salient\Tests\Sync\Entity\Comment;

/**
 * Syncs Comment objects with a backend
 *
 * @method Comment createComment(SyncContextInterface $ctx, Comment $comment)
 * @method Comment getComment(SyncContextInterface $ctx, int|string|null $id)
 * @method Comment updateComment(SyncContextInterface $ctx, Comment $comment)
 * @method Comment deleteComment(SyncContextInterface $ctx, Comment $comment)
 * @method iterable<Comment> getComments(SyncContextInterface $ctx)
 *
 * @generated
 */
interface CommentProvider extends SyncProviderInterface {}
