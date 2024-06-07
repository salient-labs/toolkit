<?php declare(strict_types=1);

namespace Salient\PHPStan\Type\Core;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NeverType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use Salient\Utility\Arr;
use Salient\Utility\Get;

class GetCoalesceMethodReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    /**
     * @codeCoverageIgnore
     */
    public function getClass(): string
    {
        return Get::class;
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
        $null = new NullType();

        $args = $methodCall->getArgs();

        if ($args === []) {
            return $null;
        }

        $types = [];
        $last = null;
        foreach ($args as $arg) {
            $type = $scope->getType($arg->value);

            // Unpack variadic arguments
            if ($arg->unpack) {
                $type = $type->getIterableValueType();

                if ($type instanceof NeverType) {
                    continue;
                }

                if ($type instanceof UnionType) {
                    $type = $type->getTypes();
                }
            }

            foreach (Arr::wrap($type) as $type) {
                $last = $type;
                $isNull = $null->isSuperTypeOf($type);
                if ($isNull->yes()) {
                    continue;
                }
                if ($isNull->no()) {
                    break 2;
                }
                $types[] = TypeCombinator::removeNull($type);
            }
        }

        if ($last) {
            $types[] = $last;
        }

        if ($types === []) {
            return $null;
        }

        return TypeCombinator::union(...$types);
    }
}
