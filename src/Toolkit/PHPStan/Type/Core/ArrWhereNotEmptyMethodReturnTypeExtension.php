<?php declare(strict_types=1);

namespace Salient\PHPStan\Type\Core;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ErrorType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use Salient\Utility\Arr;
use Stringable;

class ArrWhereNotEmptyMethodReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
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
        return $methodReflection->getName() === 'whereNotEmpty';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): ?Type {
        $args = $methodCall->getArgs();

        if ($args === []) {
            return new ErrorType();
        }

        $type = $scope->getType($args[0]->value);

        if ($type->isIterable()->no()) {
            return new ErrorType();
        }

        $empty = new UnionType([
            new ConstantStringType(''),
            new ConstantBooleanType(false),
            new NullType(),
        ]);

        if (!$type->isConstantArray()->yes()) {
            $valueType = $type->getIterableValueType();
            if ($valueType->isSuperTypeOf($empty)->no()) {
                return $type;
            }
            $keyType = $type->getIterableKeyType();
            $valueType = TypeCombinator::remove($valueType, $empty);
            return new ArrayType($keyType, $valueType);
        }

        $stringable = new ObjectType(Stringable::class);

        $arrays = $type->getConstantArrays();
        $types = [];
        foreach ($arrays as $array) {
            $builder = ConstantArrayTypeBuilder::createEmpty();
            $keyTypes = $array->getKeyTypes();
            $valueTypes = $array->getValueTypes();
            foreach ($keyTypes as $i => $keyType) {
                $valueType = $valueTypes[$i];
                $isEmpty = $empty->isSuperTypeOf($valueType);
                if ($isEmpty->yes()) {
                    continue;
                }
                $valueType = TypeCombinator::remove($valueType, $empty);
                $optional = $array->isOptionalKey($i)
                    || $isEmpty->maybe()
                    || !$valueType->isSuperTypeOf($stringable)->no();
                $builder->setOffsetValueType($keyType, $valueType, $optional);
            }
            $types[] = $builder->getArray();
        }

        return TypeCombinator::union(...$types);
    }
}
