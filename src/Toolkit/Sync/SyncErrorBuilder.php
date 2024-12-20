<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Sync\ErrorType;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\AbstractBuilder;

/**
 * @method $this errorType(ErrorType::* $value) Error type
 * @method $this message(string $value) `sprintf()` format string that explains the error
 * @method $this values(list<mixed[]|object|int|float|string|bool|null>|null $value) Values applied to the message format string. Default: `[$entityName]`
 * @method $this level(Level::* $value) Error severity/message level
 * @method $this entity(SyncEntityInterface|null $value) Entity associated with the error
 * @method $this entityName(string|null $value) Display name of the entity associated with the error. Default: `$entity->getUri()`
 * @method $this provider(SyncProviderInterface|null $value) Sync provider associated with the error. Default: `$entity->getProvider()`
 *
 * @extends AbstractBuilder<SyncError>
 *
 * @generated
 */
final class SyncErrorBuilder extends AbstractBuilder
{
    /**
     * @internal
     */
    protected static function getService(): string
    {
        return SyncError::class;
    }
}
