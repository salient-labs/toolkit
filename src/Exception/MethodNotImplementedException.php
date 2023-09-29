<?php declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Utility\Reflect;
use ReflectionClass;

/**
 * Thrown when an unimplemented method is called
 */
class MethodNotImplementedException extends \Lkrms\Exception\Exception
{
    /**
     * @var class-string
     */
    protected $Class;

    /**
     * @var string
     */
    protected $Method;

    /**
     * @var class-string
     */
    protected $PrototypeClass;

    /**
     * @param class-string $class
     * @param class-string|null $prototypeClass
     */
    public function __construct(string $class, string $method, ?string $prototypeClass = null)
    {
        if (!$prototypeClass &&
                ($_class = new ReflectionClass($class))->hasMethod($method)) {
            $prototypeClass = Reflect::getMethodPrototypeClass($_class->getMethod($method))->getName();
        }

        $this->Class = $class;
        $this->Method = $method;
        $this->PrototypeClass = $prototypeClass;

        parent::__construct(sprintf(
            '%s has not implemented %s::%s()',
            $class,
            $prototypeClass,
            $method
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
