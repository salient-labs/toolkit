<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Salient\Core\Contract\SerializeRulesInterface;

/**
 * Instructions for serializing nested sync entities
 */
interface ISyncSerializeRules extends SerializeRulesInterface
{
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
