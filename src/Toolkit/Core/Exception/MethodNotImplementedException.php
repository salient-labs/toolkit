<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Core\Exception\MethodNotImplementedException as MethodNotImplementedExceptionInterface;
use Salient\Utility\Reflect;
use LogicException;
use ReflectionMethod;

/**
 * @api
 */
class MethodNotImplementedException extends LogicException implements MethodNotImplementedExceptionInterface
{
    /** @var class-string */
    protected string $Class;
    protected string $Method;
    /** @var class-string */
    protected string $PrototypeClass;

    /**
     * @api
     *
     * @param class-string $class
     * @param class-string|null $prototypeClass
     */
    public function __construct(string $class, string $method, ?string $prototypeClass = null)
    {
        $prototypeClass ??=
            Reflect::getPrototypeClass(
                new ReflectionMethod($class, $method)
            )->name;

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
     * @inheritDoc
     */
    public function getClass(): string
    {
        return $this->Class;
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->Method;
    }

    /**
     * @inheritDoc
     */
    public function getPrototypeClass(): string
    {
        return $this->PrototypeClass;
    }
}
