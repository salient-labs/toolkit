<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity\Provider;

use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Tests\Sync\Entity\Comment;

/**
 * Syncs Comment objects with a backend
 *
 * @method Comment createComment(ISyncContext $ctx, Comment $comment)
 * @method Comment getComment(ISyncContext $ctx, int|string|null $id)
 * @method Comment updateComment(ISyncContext $ctx, Comment $comment)
 * @method Comment deleteComment(ISyncContext $ctx, Comment $comment)
 * @method iterable<Comment> getComments(ISyncContext $ctx)
 *
 * @generated
 */
interface CommentProvider extends ISyncProvider {}
