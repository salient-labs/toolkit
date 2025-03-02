<?php declare(strict_types=1);

namespace Salient\PHPStan\Utility;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use Salient\PHPStan\Internal\ReturnTypeExtensionTrait;
use Salient\Utility\Arr;

class ArrWhereNotEmptyReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    use ReturnTypeExtensionTrait;

    public function getClass(): string
    {
        return Arr::class;
    }

    public function isStaticMethodSupported(
        MethodReflection $methodReflection
    ): bool {
        return $methodReflection->getName() === 'whereNotEmpty';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): ?Type {
        return ($args = $this->getArgTypes($methodCall, $scope))
            && ($arg = $args[0])->isIterable()->yes()
                ? ($arg->isConstantArray()->yes()
                    ? $this->getArrayTypeFromConstantArrayType($arg, $this->getEmptyType(), $this->getMaybeEmptyType())
                    : $this->getArrayTypeFromIterableType($arg, $this->getEmptyType()))
                : new NeverType();
    }
}
