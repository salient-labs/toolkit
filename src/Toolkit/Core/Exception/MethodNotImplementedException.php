<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Utility\Reflect;
use ReflectionMethod;

/**
 * @api
 */
class MethodNotImplementedException extends \LogicException
{
    /** @var class-string */
    protected string $Class;
    protected string $Method;
    /** @var class-string */
    protected string $PrototypeClass;

    /**
     * @param class-string $class
     * @param class-string|null $prototypeClass
     */
    public function __construct(string $class, string $method, ?string $prototypeClass = null)
    {
        $prototypeClass ??= Reflect::getPrototypeClass(
            new ReflectionMethod($class, $method)
        )->getName();

        $this->Class = $class;
        $this->Method = $method;
        $this->PrototypeClass = $prototypeClass;

        parent::__construct(sprintf(
            '%s does not implement %s::%s()',
            $class,
            $prototypeClass,
            $method,
        ));
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->Class;
    }

    public function getMethod(): string
    {
        return $this->Method;
    }

    /**
     * @return class-string
     */
    public function getPrototypeClass(): string
    {
        return $this->PrototypeClass;
    }
}
