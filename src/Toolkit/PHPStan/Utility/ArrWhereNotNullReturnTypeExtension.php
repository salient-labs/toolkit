<?php declare(strict_types=1);

namespace Salient\PHPStan\Utility;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NeverType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use Salient\PHPStan\Internal\ReturnTypeExtensionTrait;
use Salient\Utility\Arr;

/**
 * @internal
 */
class ArrWhereNotNullReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    use ReturnTypeExtensionTrait;

    public function getClass(): string
    {
        return Arr::class;
    }

    public function isStaticMethodSupported(
        MethodReflection $methodReflection
    ): bool {
        return $methodReflection->getName() === 'whereNotNull';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): ?Type {
        return ($args = $this->getArgTypes($methodCall, $scope, true))
            && ($arg = $args[0])->isIterable()->yes()
                ? ($arg->isConstantArray()->yes()
                    ? $this->getArrayTypeFromConstantArrayType($arg->Type, new NullType())
                    : $this->getArrayTypeFromIterableType($arg->Type, new NullType()))
                : new NeverType();
    }
}
