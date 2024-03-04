<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\HasContainer;
use Salient\Contract\Core\SerializeRulesInterface;

/**
 * Instructions for serializing nested sync entities
 *
 * @extends HasContainer<ContainerInterface>
 */
interface SyncSerializeRulesInterface extends SerializeRulesInterface, HasContainer
{
    /**
     * Values are being serialized for an entity store
     */
    public const SYNC_STORE = 1;

    /**
     * A bitmask of enabled flags
     *
     * @return int-mask-of<SyncSerializeRulesInterface::*>
     */
    public function getFlags(): int;

    /**
     * Remove CanonicalId from sync entities?
     */
    public function getRemoveCanonicalId(): bool;

    /**
     * Set the value of RemoveCanonicalId on a copy of the instance
     *
     * @return $this
     */
    public function withRemoveCanonicalId(?bool $value);
}
