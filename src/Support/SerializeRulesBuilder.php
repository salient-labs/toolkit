<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;

/**
 * A fluent interface for creating SerializeRules objects
 *
 * @method static $this build(?IContainer $container = null) Create a new SerializeRulesBuilder (syntactic sugar for 'new SerializeRulesBuilder()')
 * @method $this inherit(?SerializeRules $value)
 * @method $this detectRecursion(bool $value = true) Check for recursion? (see {@see SerializeRules::$DetectRecursion})
 * @method $this doNotSerialize(array $value) Keys to remove from nested class arrays (see {@see SerializeRules::$DoNotSerialize})
 * @method $this onlySerializeId(array $value) Keys to replace with identifiers in nested class arrays (see {@see SerializeRules::$OnlySerializeId})
 * @method $this idKeyCallback(?callable $value) A callback that returns the key to use when a nested object is replaced with its identifier during serialization (see {@see SerializeRules::$IdKeyCallback})
 * @method $this onlySerializePlaceholders(bool $value = true) Replace nested objects with placeholders? (see {@see SerializeRules::$OnlySerializePlaceholders})
 * @method SerializeRules go() Return a new SerializeRules object
 *
 * @uses SerializeRules
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Support\SerializeRules' --static-builder='build' --terminator='go'
 */
final class SerializeRulesBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return SerializeRules::class;
    }
}
