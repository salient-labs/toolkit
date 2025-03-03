<?php declare(strict_types=1);

namespace Salient\PHPStan\Utility;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use Salient\PHPStan\Internal\ReturnTypeExtensionTrait;
use Salient\Utility\Arr;

class ArrFlattenReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    use ReturnTypeExtensionTrait;

    public function getClass(): string
    {
        return Arr::class;
    }

    public function isStaticMethodSupported(
        MethodReflection $methodReflection
    ): bool {
        return $methodReflection->getName() === 'flatten';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): ?Type {
        if ($args = $this->getArgTypes($methodCall, $scope, true)) {
            $limit = -1;
            if ($type = $args[1] ?? null) {
                if (
                    !$type->isConstantScalarValue()->yes()
                    || !$type->isInteger()->yes()
                ) {
                    return null;
                }
                /** @var int */
                $limit = $type->getConstantScalarValues()[0];
            }

            $preserveKeys = false;
            if ($type = $args[2] ?? null) {
                if (
                    !$type->isConstantScalarValue()->yes()
                    || !$type->isBoolean()->yes()
                ) {
                    return null;
                }
                /** @var bool */
                $preserveKeys = $type->getConstantScalarValues()[0];
            }

            $type = $args[0]->Type;
            if ($type->isIterable()->no()) {
                return new NeverType();
            }

            $arrayKey = $this->getArrayKeyType();
            [$keyTypes, $valueTypes, $isOptional, $isConstant] = $this->getIterableTypes($type);
            $iterablesAreConstant = $isConstant;
            do {
                $flattenedKeys = [];
                $flattenedValues = [];
                $flattenedIsOptional = [];
                $addKeyTypes = function (
                    array $keyTypes,
                    bool $isConstant,
                    bool $valueTypeAdded
                ) use ($preserveKeys, $arrayKey, &$flattenedKeys) {
                    if (!$isConstant && $preserveKeys && $valueTypeAdded) {
                        $keyType = TypeCombinator::union(...array_values($keyTypes));
                        $flattenedKeys[] = $arrayKey->isSuperTypeOf($keyType)->yes()
                            ? $keyType
                            : TypeCombinator::union(
                                TypeCombinator::intersect($keyType, $arrayKey),
                                new IntegerType(),
                            );
                    }
                };

                $i = null;
                $fromIterable = false;
                $valueTypeAdded = false;
                foreach ($valueTypes as $index => $valueType) {
                    if (!$valueType->isIterable()->yes() || !$limit) {
                        $flattenedValues[] = $valueType;
                        if ($isConstant) {
                            if ($preserveKeys) {
                                $flattenedKeys[] = $this->getArrKey($keyTypes[$index], $i);
                            }
                            $flattenedIsOptional[] = $isOptional[$index] ?? false;
                        }
                        $valueTypeAdded = true;
                        continue;
                    }
                    [$_keyTypes, $_valueTypes, $_isOptional, $_isConstant] = $this->getIterableTypes($valueType);
                    $iterablesAreConstant = $iterablesAreConstant && $_isConstant;
                    $fromIterable = true;
                    $_valueTypeAdded = false;
                    foreach ($_valueTypes as $_index => $_valueType) {
                        $flattenedValues[] = $_valueType;
                        if ($_isConstant) {
                            if ($preserveKeys) {
                                $flattenedKeys[] = $limit === 1 || !$_valueType->isIterable()->yes()
                                    ? $this->getArrKey($_keyTypes[$_index], $i)
                                    : new ConstantIntegerType(array_key_last($flattenedValues));
                            }
                            $flattenedIsOptional[] = $_isOptional[$_index] ?? false;
                        }
                        $_valueTypeAdded = true;
                    }
                    $addKeyTypes($_keyTypes, $_isConstant, $_valueTypeAdded);
                }
                $addKeyTypes($keyTypes, $isConstant, $valueTypeAdded);
                $keyTypes = $flattenedKeys;
                $valueTypes = $flattenedValues;
                $isOptional = $flattenedIsOptional;
                $isConstant = $iterablesAreConstant;
                $limit--;
            } while ($fromIterable && $limit);

            if ($isConstant) {
                $builder = ConstantArrayTypeBuilder::createEmpty();
                foreach ($valueTypes as $index => $valueType) {
                    $builder->setOffsetValueType(
                        $preserveKeys ? $keyTypes[$index] : null,
                        $valueType,
                        $isOptional[$index],
                    );
                }
                return $builder->getArray();
            }
            return new ArrayType(
                $preserveKeys ? TypeCombinator::union(...$keyTypes) : new MixedType(),
                TypeCombinator::union(...$valueTypes),
            );
        }
        return new NeverType();
    }

    /**
     * @return array{Type[],Type[],true[],bool} An array with values:
     * - key types
     * - value types
     * - optional key index
     * - is constant?
     */
    private function getIterableTypes(Type $type): array
    {
        return $type->isConstantArray()->yes()
            && count($arrays = $type->getConstantArrays()) === 1
                ? [
                    $arrays[0]->getKeyTypes(),
                    $arrays[0]->getValueTypes(),
                    array_fill_keys($arrays[0]->getOptionalKeys(), true),
                    true,
                ]
                : [
                    $this->splitType($type->getIterableKeyType()),
                    $this->splitType($type->getIterableValueType()),
                    [],
                    false,
                ];
    }

    /**
     * @return Type[]
     */
    private function splitType(Type $type): array
    {
        return $type instanceof UnionType ? $type->getTypes() : [$type];
    }
}
