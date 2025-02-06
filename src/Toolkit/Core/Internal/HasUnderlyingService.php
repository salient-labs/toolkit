<?php declare(strict_types=1);

namespace Salient\Core\Internal;

use Salient\Utility\Arr;
use LogicException;
use ReflectionClass;

/**
 * @internal
 *
 * @template TService of object
 */
trait HasUnderlyingService
{
    /**
     * @return class-string<TService>|array{class-string<TService>,class-string<TService>[]|class-string<TService>}
     */
    abstract protected static function getService();

    /**
     * @return class-string<TService>|null
     */
    private static function getInstantiableService(): ?string
    {
        [$name, $list] = self::getNormalisedService();

        foreach (Arr::extend([$name], ...$list) as $service) {
            if (
                !class_exists($service)
                || !(new ReflectionClass($service))->isInstantiable()
            ) {
                continue;
            }

            if ($service !== $name && !is_a($service, $name, true)) {
                throw new LogicException(sprintf(
                    '%s does not inherit %s',
                    $service,
                    $name,
                ));
            }

            return $service;
        }

        return null;
    }

    /**
     * @return array{class-string<TService>,class-string<TService>[]}
     */
    private static function getNormalisedService(): array
    {
        $service = static::getService();

        if (is_string($service)) {
            return [$service, []];
        }

        return [$service[0], array_values((array) $service[1])];
    }
}
