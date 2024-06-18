<?php declare(strict_types=1);

namespace Salient\PHPStan\Type\Core;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NeverType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use Salient\Utility\Arr;
use Salient\Utility\Str;

class StrCoalesceReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    /**
     * @codeCoverageIgnore
     */
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
        $null = new NullType();

        $args = $methodCall->getArgs();

        if ($args === []) {
            return $null;
        }

        $empty = new UnionType([new ConstantStringType(''), $null]);

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
                $isEmpty = $empty->isSuperTypeOf($type);
                if ($isEmpty->yes()) {
                    continue;
                }
                if ($isEmpty->no()) {
                    break 2;
                }
                $types[] = TypeCombinator::remove($type, $empty);
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
