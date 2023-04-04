<?php declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Facade\Reflect;
use ReflectionClass;

/**
 * Thrown when an unimplemented method is called
 *
 */
class MethodNotImplementedException extends \Lkrms\Exception\Exception
{
    /**
     * @var string
     */
    protected $Class;

    /**
     * @var string
     */
    protected $Method;

    /**
     * @var string
     */
    protected $PrototypeClass;

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
}
