<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Core\ExceptionInterface;
use Salient\Core\Concern\ExceptionTrait;
use Salient\Core\Utility\Reflect;
use ReflectionMethod;

/**
 * @api
 */
class MethodNotImplementedException extends BadMethodCallException implements ExceptionInterface
{
    use ExceptionTrait;

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
        if ($prototypeClass === null) {
            $prototypeClass = Reflect::getMethodPrototypeClass(
                new ReflectionMethod($class, $method)
            )->getName();
        }

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
