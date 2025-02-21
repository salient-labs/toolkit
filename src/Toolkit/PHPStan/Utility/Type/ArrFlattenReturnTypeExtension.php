<?php declare(strict_types=1);

namespace Salient\PHPStan\Utility\Type;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use Salient\Utility\Arr;

class ArrFlattenReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
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
        return $methodReflection->getName() === 'flatten';
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

        $limit = -1;
        if (isset($args[1])) {
            $type = $scope->getType($args[1]->value);
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
        if (isset($args[2])) {
            $type = $scope->getType($args[2]->value);
            if (
                !$type->isConstantScalarValue()->yes()
                || !$type->isBoolean()->yes()
            ) {
                return null;
            }
            /** @var bool */
            $preserveKeys = $type->getConstantScalarValues()[0];
        }

        $type = $scope->getType($args[0]->value);

        if ($type->isIterable()->no()) {
            return null;
        }

        $arrayKey = new UnionType([new IntegerType(), new StringType()]);

        [$keyTypes, $valueTypes, $isOptional, $isConstant] = $this->getIterableTypes($type);
        $isAlwaysConstant = $isConstant;

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
                    $type = TypeCombinator::union(...array_values($keyTypes));
                    $flattenedKeys[] = $arrayKey->isSuperTypeOf($type)->yes()
                        ? $type
                        : TypeCombinator::union(
                            TypeCombinator::intersect($type, $arrayKey),
                            new IntegerType(),
                        );
                }
            };

            $i = null;
            $fromIterable = false;
            $valueTypeAdded = false;
            foreach ($valueTypes as $index => $type) {
                if (!$type->isIterable()->yes() || !$limit) {
                    $flattenedValues[] = $type;
                    if ($isConstant) {
                        if ($preserveKeys) {
                            $flattenedKeys[] = $this->getKey($keyTypes[$index], $i);
                        }
                        $flattenedIsOptional[] = $isOptional[$index];
                    }
                    $valueTypeAdded = true;
                    continue;
                }

                [$_keyTypes, $_valueTypes, $_isOptional, $_isConstant] = $this->getIterableTypes($type);
                $isAlwaysConstant = $isAlwaysConstant && $_isConstant;

                $fromIterable = true;
                $_valueTypeAdded = false;
                foreach ($_valueTypes as $index => $type) {
                    $flattenedValues[] = $type;
                    if ($_isConstant) {
                        if ($preserveKeys) {
                            $flattenedKeys[] = $limit === 1 || !$type->isIterable()->yes()
                                ? $this->getKey($_keyTypes[$index], $i)
                                : new ConstantIntegerType(array_key_last($flattenedValues));
                        }
                        $flattenedIsOptional[] = $_isOptional[$index];
                    }
                    $_valueTypeAdded = true;
                }

                $addKeyTypes($_keyTypes, $_isConstant, $_valueTypeAdded);
            }

            $addKeyTypes($keyTypes, $isConstant, $valueTypeAdded);

            $keyTypes = $flattenedKeys;
            $valueTypes = $flattenedValues;
            $isOptional = $flattenedIsOptional;
            $isConstant = $isAlwaysConstant;

            $limit--;
        } while ($fromIterable && $limit);

        if ($isConstant) {
            $builder = ConstantArrayTypeBuilder::createEmpty();
            foreach ($valueTypes as $index => $type) {
                $builder->setOffsetValueType(
                    $preserveKeys ? $keyTypes[$index] : null,
                    $type,
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

    /**
     * @return array{Type[],Type[],bool[],bool}
     */
    private function getIterableTypes(Type $type): array
    {
        if (
            $type->isConstantArray()->yes()
            && count($arrays = $type->getConstantArrays()) === 1
        ) {
            $keyTypes = $arrays[0]->getKeyTypes();
            $optionalKeys = $arrays[0]->getOptionalKeys();
            foreach (array_keys($keyTypes) as $key) {
                $isOptional[$key] = in_array($key, $optionalKeys, true);
            }
            return [
                $keyTypes,
                $arrays[0]->getValueTypes(),
                $isOptional ?? [],
                true,
            ];
        }

        $keyType = $type->getIterableKeyType();
        $valueType = $type->getIterableValueType();
        return [
            $keyType instanceof UnionType
                ? $keyType->getTypes()
                : [$keyType],
            $valueType instanceof UnionType
                ? $valueType->getTypes()
                : [$valueType],
            [],
            false,
        ];
    }

    private function getKey(Type $type, ?int &$i): Type
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
