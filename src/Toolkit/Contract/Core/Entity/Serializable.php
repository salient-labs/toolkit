<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

/**
 * @api
 */
interface Serializable
{
    /**
     * Get serialization rules
     *
     * @return SerializeRulesInterface<static>
     */
    public static function getSerializeRules(): SerializeRulesInterface;
}
