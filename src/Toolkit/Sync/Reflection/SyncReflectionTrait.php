<?php declare(strict_types=1);

namespace Salient\Sync\Reflection;

use ReflectionException;

/**
 * @internal
 */
trait SyncReflectionTrait
{
    /**
     * @param object|string $objectOrClass
     * @param class-string $interface
     */
    protected function assertImplements($objectOrClass, string $interface): void
    {
        if (!is_a($objectOrClass, $interface, true)) {
            if (!is_string($objectOrClass)) {
                $objectOrClass = get_class($objectOrClass);
            }
            throw new ReflectionException(sprintf(
                '%s does not implement %s',
                $objectOrClass,
                $interface,
            ));
        }
    }
}
