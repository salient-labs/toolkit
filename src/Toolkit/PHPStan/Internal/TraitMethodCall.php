<?php declare(strict_types=1);

namespace Salient\PHPStan\Internal;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Type\Type;

/**
 * @internal
 */
final class TraitMethodCall
{
    public Type $CalledOn;
    public ExtendedMethodReflection $Method;
    public ClassReflection $MethodClass;
    public ClassReflection $MethodTrait;
    public string $MethodName;

    public function __construct(
        Type $calledOn,
        ExtendedMethodReflection $method,
        ClassReflection $methodClass,
        ClassReflection $methodTrait,
        string $methodName
    ) {
        $this->CalledOn = $calledOn;
        $this->Method = $method;
        $this->MethodClass = $methodClass;
        $this->MethodTrait = $methodTrait;
        $this->MethodName = $methodName;
    }
}
