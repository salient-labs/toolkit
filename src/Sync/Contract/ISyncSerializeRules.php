<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Contract\ISerializeRules;

/**
 * Instructions for serializing nested sync entities
 */
interface ISyncSerializeRules extends ISerializeRules
{
    /**
     * Remove CanonicalId from sync entities?
     *
     * @return bool
     */
    public function getRemoveCanonicalId(): bool;

    /**
     * Set the value of RemoveCanonicalId on a copy of the instance
     *
     * @return $this
     */
    public function withRemoveCanonicalId(?bool $value);
}
