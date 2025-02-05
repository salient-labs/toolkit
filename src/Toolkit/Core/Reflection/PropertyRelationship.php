<?php declare(strict_types=1);

namespace Salient\Core\Reflection;

use Salient\Contract\Core\Entity\Relatable;

/**
 * @api
 */
final class PropertyRelationship
{
    /**
     * Property name
     */
    public string $Name;

    /**
     * Relationship type
     *
     * @var Relatable::*
     */
    public int $Type;

    /**
     * Target class
     *
     * @var class-string<Relatable>
     */
    public string $Target;

    /**
     * @internal
     *
     * @param Relatable::* $type
     * @param class-string<Relatable> $target
     */
    public function __construct(string $name, int $type, string $target)
    {
        $this->Name = $name;
        $this->Type = $type;
        $this->Target = $target;
    }
}
