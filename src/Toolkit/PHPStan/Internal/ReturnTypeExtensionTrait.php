<?php declare(strict_types=1);

namespace Salient\PHPStan\Internal;

use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use Stringable;

/**
 * @internal
 */
trait ReturnTypeExtensionTrait
{
    /**
     * @return Type[]
     */
    private function getArgTypes(CallLike $node, Scope $scope, ?Type $emptyVariadicType = null): array
    {
        foreach ($node->getArgs() as $arg) {
            $type = $scope->getType($arg->value);
            if ($arg->unpack) {
                if (
                    $type->isConstantArray()->yes()
                    && count($arrays = $type->getConstantArrays()) === 1
                    && !($array = $arrays[0])->getOptionalKeys()
                ) {
                    foreach ($array->getValueTypes() as $type) {
                        $types[] = $type;
                    }
                    continue;
                }
                $isNonEmpty = $type->isIterableAtLeastOnce();
                $type = $type->getIterableValueType();
                if ($isNonEmpty->maybe()) {
                    if ($emptyVariadicType) {
                        $type = TypeCombinator::union($type, $emptyVariadicType);
                    } else {
                        continue;
                    }
                } elseif ($isNonEmpty->no()) {
                    continue;
                }
            }
            $types[] = $type;
        }
        return $types ?? [];
    }

    private function getArrayTypeFromIterableType(
        Type $iterableType,
        ?Type $removeFromValueType = null
    ): Type {
        $arrayKey = new UnionType([new IntegerType(), new StringType()]);
        $keyType = $iterableType->getIterableKeyType();
        $valueType = $iterableType->getIterableValueType();
        $changed = false;
        if (!$arrayKey->isSuperTypeOf($keyType)->yes()) {
            $keyType = TypeCombinator::union(
                TypeCombinator::intersect($keyType, $arrayKey),
                new IntegerType(),
            );
            $changed = true;
        }
        if (
            $removeFromValueType
            && !$valueType->isSuperTypeOf($removeFromValueType)->no()
        ) {
            $valueType = TypeCombinator::remove($valueType, $removeFromValueType);
            $changed = true;
        }
        return $changed || !$iterableType->isArray()->yes()
            ? new ArrayType($keyType, $valueType)
            : $iterableType;
    }

    private function getArrayTypeFromConstantArrayType(
        Type $constantArrayType,
        Type $removeFromValueType,
        ?Type $maybeRemoveFromValueType = null
    ): Type {
        $arrays = [];
        foreach ($constantArrayType->getConstantArrays() as $array) {
            $builder = ConstantArrayTypeBuilder::createEmpty();
            $keyTypes = $array->getKeyTypes();
            $valueTypes = $array->getValueTypes();
            foreach ($keyTypes as $i => $keyType) {
                $valueType = $valueTypes[$i];
                if ($removeFromValueType->isSuperTypeOf($valueType)->yes()) {
                    continue;
                }
                $optional = false;
                if (!$valueType->isSuperTypeOf($removeFromValueType)->no()) {
                    $valueType = TypeCombinator::remove($valueType, $removeFromValueType);
                    $optional = true;
                }
                $optional = $optional || $array->isOptionalKey($i) || (
                    $maybeRemoveFromValueType
                    && !$valueType->isSuperTypeOf($maybeRemoveFromValueType)->no()
                );
                $builder->setOffsetValueType($keyType, $valueType, $optional);
            }
            $arrays[] = $builder->getArray();
        }
        return TypeCombinator::union(...$arrays);
    }

    private function getEmptyType(): Type
    {
        return new UnionType([
            new ConstantStringType(''),
            new ConstantBooleanType(false),
            new NullType(),
        ]);
    }

    private function getMaybeEmptyType(): Type
    {
        return new ObjectType(Stringable::class);
    }
}
