<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;

/**
 * A fluent interface for creating SerializeRules objects
 *
 * @method static $this build(?IContainer $container = null) Create a new SerializeRulesBuilder (syntactic sugar for 'new SerializeRulesBuilder()')
 * @method static $this inherit(SerializeRules|SerializeRulesBuilder|null $value)
 * @method static $this detectRecursion(bool $value = true) Check for recursion? (see {@see SerializeRules::$DetectRecursion})
 * @method static $this doNotSerialize(array $value) Keys to remove from nested class arrays (see {@see SerializeRules::$DoNotSerialize})
 * @method static $this onlySerializeId(array $value) Keys to replace with identifiers in nested class arrays (see {@see SerializeRules::$OnlySerializeId})
 * @method static $this idKeyCallback(?callable $value) A callback that returns the key to use when a nested object is replaced with its identifier during serialization (see {@see SerializeRules::$IdKeyCallback})
 * @method static $this onlySerializePlaceholders(bool $value = true) Replace nested objects with placeholders? (see {@see SerializeRules::$OnlySerializePlaceholders})
 * @method static SerializeRules go() Return a new SerializeRules object
 * @method static SerializeRules|null resolve(SerializeRules|SerializeRulesBuilder|null $object) Resolve a SerializeRulesBuilder or SerializeRules object to a SerializeRules object
 *
 * @uses SerializeRules
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Support\SerializeRules' --static-builder='build' --terminator='go' --static-resolver='resolve'
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
