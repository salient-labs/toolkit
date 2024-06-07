<?php declare(strict_types=1);

namespace Salient\Core\Concern;

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
    /** @var array<class-string<static>,class-string<TService>> */
    private static array $ServiceNames = [];
    /** @var array<class-string<static>,array<class-string<TService>>> */
    private static array $ServiceLists = [];
    /** @var array<class-string<static>,true> */
    private static array $LoadedServices = [];

    /** @return class-string<TService>|array<class-string<TService>,class-string<TService>|array<class-string<TService>>> */
    abstract protected static function getService();

    /**
     * @return class-string<TService>|null
     */
    private static function getInstantiableService(): ?string
    {
        $serviceName = self::getServiceName();
        $serviceList = self::getServiceList();

        foreach ($serviceList as $service) {
            if (
                !class_exists($service)
                || !(new ReflectionClass($service))->isInstantiable()
            ) {
                continue;
            }

            if ($service !== $serviceName && !is_a($service, $serviceName, true)) {
                // @codeCoverageIgnoreStart
                throw new LogicException(sprintf(
                    '%s does not inherit %s: %s::getService()',
                    $service,
                    $serviceName,
                    static::class,
                ));
                // @codeCoverageIgnoreEnd
            }

            return $service;
        }

        return null;
    }

    /**
     * @return class-string<TService>
     */
    private static function getServiceName(): string
    {
        self::loadServiceList();

        return self::$ServiceNames[static::class];
    }

    /**
     * @return array<class-string<TService>>
     */
    private static function getServiceList(): array
    {
        self::loadServiceList();

        return self::$ServiceLists[static::class];
    }

    private static function loadServiceList(): void
    {
        if (self::$LoadedServices[static::class] ?? false) {
            return;
        }

        self::doLoadServiceList($serviceName, $serviceList);
        self::$ServiceNames[static::class] = $serviceName;
        self::$ServiceLists[static::class] = $serviceList;
        self::$LoadedServices[static::class] = true;
    }

    /**
     * @param class-string<TService> $serviceName
     * @param-out class-string<TService> $serviceName
     * @param array<class-string<TService>> $serviceList
     * @param-out array<class-string<TService>> $serviceList
     */
    private static function doLoadServiceList(?string &$serviceName, ?array &$serviceList): void
    {
        $service = static::getService();

        if (is_string($service)) {
            $serviceName = $service;
            $serviceList = [$service];
            return;
        }

        if (is_array($service) && count($service) === 1) {
            $name = array_key_first($service);
            if (is_string($name)) {
                $value = $service[$name];
                if (is_string($value)) {
                    $serviceName = $name;
                    $serviceList = Arr::extend([$name], $value);
                    return;
                }
                if (Arr::isListOfString($value)) {
                    $serviceName = $name;
                    $serviceList = Arr::extend([$name], ...$value);
                    return;
                }
            }
        }

        // @codeCoverageIgnoreStart
        throw new LogicException(sprintf(
            'Invalid service: %s::getService()',
            static::class,
        ));
        // @codeCoverageIgnoreEnd
    }

    private static function unloadServiceList(): void
    {
        unset(self::$LoadedServices[static::class]);
        unset(self::$ServiceLists[static::class]);
        unset(self::$ServiceNames[static::class]);
    }

    private static function unloadAllServiceLists(): void
    {
        self::$ServiceNames = [];
        self::$ServiceLists = [];
        self::$LoadedServices = [];
    }
}
