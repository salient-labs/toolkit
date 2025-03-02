<?php declare(strict_types=1);

namespace Salient\PHPStan\Utility;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Salient\PHPStan\Internal\ReturnTypeExtensionTrait;
use Salient\Utility\Str;

class StrCoalesceReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    use ReturnTypeExtensionTrait;

    public function getClass(): string
    {
        return Str::class;
    }

    public function isStaticMethodSupported(
        MethodReflection $methodReflection
    ): bool {
        return $methodReflection->getName() === 'coalesce';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): ?Type {
        $empty = $this->getEmptyType();
        $args = $this->getArgTypes($methodCall, $scope, new NullType());
        $arg = new NullType();
        foreach ($args as $arg) {
            $isEmpty = $empty->isSuperTypeOf($arg);
            if ($isEmpty->maybe()) {
                $types[] = TypeCombinator::remove($arg, $empty)->toString();
            } elseif ($isEmpty->no()) {
                $types[] = $arg->toString();
                break;
            }
        }
        $isNull = $arg->isNull();
        if ($isNull->maybe()) {
            $types[] = TypeCombinator::addNull(TypeCombinator::removeNull($arg)->toString());
        } elseif ($isNull->no()) {
            $types[] = $arg->toString();
        } else {
            $types[] = $arg;
        }
        return TypeCombinator::union(...$types);
    }
}
