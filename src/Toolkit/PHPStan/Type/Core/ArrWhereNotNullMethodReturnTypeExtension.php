<?php declare(strict_types=1);

namespace Salient\PHPStan\Type\Core;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Salient\Utility\Arr;

class ArrWhereNotNullMethodReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    /**
     * @codeCoverageIgnore
     */
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
        $args = $methodCall->getArgs();

        if ($args === []) {
            return null;
        }

        $type = $scope->getType($args[0]->value);

        if ($type->isIterable()->no()) {
            return null;
        }

        $null = new NullType();

        if (!$type->isConstantArray()->yes()) {
            $valueType = $type->getIterableValueType();
            if ($valueType->isSuperTypeOf($null)->no()) {
                return $type;
            }
            $keyType = $type->getIterableKeyType();
            $valueType = TypeCombinator::remove($valueType, $null);
            return new ArrayType($keyType, $valueType);
        }

        $arrays = $type->getConstantArrays();
        $types = [];
        foreach ($arrays as $array) {
            $builder = ConstantArrayTypeBuilder::createEmpty();
            $keyTypes = $array->getKeyTypes();
            $valueTypes = $array->getValueTypes();
            foreach ($keyTypes as $i => $keyType) {
                $valueType = $valueTypes[$i];
                $isNull = $null->isSuperTypeOf($valueType);
                if ($isNull->yes()) {
                    continue;
                }
                $valueType = TypeCombinator::remove($valueType, $null);
                $optional = $array->isOptionalKey($i)
                    || $isNull->maybe();
                $builder->setOffsetValueType($keyType, $valueType, $optional);
            }
            $types[] = $builder->getArray();
        }

        return TypeCombinator::union(...$types);
    }
}
