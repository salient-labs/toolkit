<?php declare(strict_types=1);

namespace Salient\Sync\Contract;

use Salient\Core\Contract\SerializeRulesInterface;

/**
 * Instructions for serializing nested sync entities
 */
interface SyncSerializeRulesInterface extends SerializeRulesInterface
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
