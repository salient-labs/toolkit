<?php declare(strict_types=1);

namespace Salient\Utility\Internal;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Salient\Contract\Container\SingletonInterface;
use Salient\Utility\Exception\UncloneableObjectException;
use Salient\Utility\Get;
use Salient\Utility\Reflect;
use Closure;
use DateTimeInterface;
use DateTimeZone;
use LogicException;
use ReflectionObject;
use UnitEnum;

/**
 * @internal
 */
final class Copier
{
    /** @var class-string[]|(Closure(object): (object|bool)) */
    private $Skip;
    /** @var int-mask-of<Get::COPY_*> */
    private int $Flags;
    /** @var array<int,object> */
    private array $Map;

    /**
     * @param class-string[]|(Closure(object): (object|bool)) $skip
     * @param int-mask-of<Get::COPY_*> $flags
     */
    public function __construct($skip, int $flags)
    {
        $this->Skip = $skip;
        $this->Flags = $flags;
    }

    /**
     * @template T
     *
     * @param T $value
     * @return T
     */
    public function copy($value)
    {
        $this->Map = [];
        try {
            return $this->doCopy($value);
        } finally {
            unset($this->Map);
        }
    }

    /**
     * @template T
     *
     * @param T $var
     * @return T
     */
    private function doCopy($var)
    {
        if (is_resource($var)) {
            return $var;
        }

        if (is_array($var)) {
            foreach ($var as $key => $value) {
                $array[$key] = $this->doCopy($value);
            }
            // @phpstan-ignore return.type
            return $array ?? [];
        }

        if (!is_object($var) || $var instanceof UnitEnum) {
            return $var;
        }

        $id = spl_object_id($var);
        if (isset($this->Map[$id])) {
            // @phpstan-ignore return.type
            return $this->Map[$id];
        }

        if ((
            !($this->Flags & Get::COPY_CONTAINERS)
            && $var instanceof PsrContainerInterface
        ) || (
            !($this->Flags & Get::COPY_SINGLETONS)
            && $var instanceof SingletonInterface
        )) {
            $this->Map[$id] = $var;
            return $var;
        }

        if ($this->Skip instanceof Closure) {
            $result = ($this->Skip)($var);
            if ($result !== false) {
                if ($result === true) {
                    $this->Map[$id] = $var;
                    return $var;
                }
                if (get_class($result) !== get_class($var)) {
                    throw new LogicException(sprintf(
                        'Closure returned %s (%s|bool expected)',
                        Get::type($result),
                        get_class($var),
                    ));
                }
                $this->Map[$id] = $result;
                if ($result !== $var) {
                    $id = spl_object_id($result);
                    $this->Map[$id] = $result;
                }
                // @phpstan-ignore return.type
                return $result;
            }
        } elseif ($this->Skip) {
            foreach ($this->Skip as $class) {
                if (is_a($var, $class)) {
                    $this->Map[$id] = $var;
                    return $var;
                }
            }
        }

        $_var = new ReflectionObject($var);

        if (!$_var->isCloneable()) {
            if ($this->Flags & Get::COPY_SKIP_UNCLONEABLE) {
                $this->Map[$id] = $var;
                return $var;
            }

            throw new UncloneableObjectException(
                sprintf('%s cannot be copied', $_var->getName())
            );
        }

        $clone = clone $var;
        $this->Map[$id] = $clone;
        $id = spl_object_id($clone);
        $this->Map[$id] = $clone;

        if (
            $this->Flags & Get::COPY_TRUST_CLONE
            && $_var->hasMethod('__clone')
        ) {
            return $clone;
        }

        if (
            $clone instanceof DateTimeInterface
            || $clone instanceof DateTimeZone
        ) {
            return $clone;
        }

        $byRef = $this->Flags & Get::COPY_BY_REFERENCE
            && !$_var->isInternal();
        foreach (Reflect::getAllProperties($_var) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (\PHP_VERSION_ID < 80100) {
                $property->setAccessible(true);
            }

            if (!$property->isInitialized($clone)) {
                continue;
            }

            $name = $property->getName();
            $value = $property->getValue($clone);
            $value = $this->doCopy($value);

            if (
                !$byRef
                || ($declaring = $property->getDeclaringClass())->isInternal()
            ) {
                $property->setValue($clone, $value);
                continue;
            }

            (function () use ($name, $value): void {
                $this->$name = &$value;
            })->bindTo($clone, $declaring->getName())();
        }

        return $clone;
    }
}
