<?php declare(strict_types=1);

namespace Salient\PHPStan\Internal;

use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantIntegerType;
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
     * @return ArgType[]
     */
    private function getArgTypes(CallLike $node, Scope $scope, bool $skipOptional = false): array
    {
        foreach ($node->getArgs() as $arg) {
            $type = $scope->getType($arg->value);
            if ($arg->unpack) {
                if (
                    $type->isConstantArray()->yes()
                    && count($arrays = $type->getConstantArrays()) === 1
                    && (!($array = $arrays[0])->getOptionalKeys() || !$skipOptional)
                ) {
                    foreach ($array->getValueTypes() as $key => $type) {
                        $optional = !$skipOptional && $array->isOptionalKey($key);
                        $types[] = new ArgType($type, $optional, true);
                    }
                    continue;
                }
                $optional = !$type->isIterableAtLeastOnce()->yes();
                if ($optional && $skipOptional) {
                    continue;
                }
                $type = $type->getIterableValueType();
            } else {
                $optional = false;
            }
            $types[] = new ArgType($type, $optional);
        }
        return $types ?? [];
    }

    private function getArrayTypeFromIterableType(
        Type $iterableType,
        ?Type $removeFromValueType = null
    ): Type {
        $arrayKey = $this->getArrayKeyType();
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

    private function getArrayKeyType(): Type
    {
        return new UnionType([
            new IntegerType(),
            new StringType(),
        ]);
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

    private function getArrKey(Type $type, ?int &$i): Type
    {
        if ($type->isConstantScalarValue()->yes()) {
            if ($type->isInteger()->yes()) {
                /** @var int */
                $key = $type->getConstantScalarValues()[0];
                if ($i === null || $key > $i) {
                    $i = $key;
                }
                return $type;
            }
            if ($type->isString()->yes()) {
                return $type;
            }
        }
        if ($i === null) {
            return new ConstantIntegerType($i = 0);
        }
        return new ConstantIntegerType(++$i);
    }
}
