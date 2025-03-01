<?php declare(strict_types=1);

namespace Salient\PHPStan\Utility;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantBooleanType;
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

        $empty = new UnionType([
            new ConstantStringType(''),
            new ConstantBooleanType(false),
            $null,
        ]);

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
                $types[] = TypeCombinator::remove(
                    self::toStringOrNull($type, $null),
                    $empty,
                );
            }
        }

        if ($last) {
            $types[] = self::toStringOrNull($last, $null);
        }

        if ($types === []) {
            return $null;
        }

        return TypeCombinator::union(...$types);
    }

    private static function toStringOrNull(Type $type, Type $null): Type
    {
        $isNull = $null->isSuperTypeOf($type);
        if ($isNull->yes()) {
            return $type;
        }
        if ($isNull->maybe()) {
            return TypeCombinator::union(
                TypeCombinator::remove($type, $null)->toString(),
                $null,
            );
        }
        return $type->toString();
    }
}
